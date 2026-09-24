<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigMergeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    /** 模拟宿主 config/nova-admin.php 只写差异，再走一遍 provider 的合并。 */
    protected function mergeHostConfig(array $hostConfig): void
    {
        config()->set('nova-admin', $hostConfig);
        (new NovaAdminServiceProvider($this->app))->register();
    }

    public function test_package_additions_are_inherited_and_host_overrides_win(): void
    {
        $this->mergeHostConfig([
            'ad_positions' => [
                'home_banner1' => '自定义首页横幅',
                'custom_spot'  => '专属广告位',
            ],
            'ads_protocol' => [
                'position_map' => ['custom_spot' => 'custom_spot'],
            ],
        ]);

        $positions = config('nova-admin.ad_positions');
        $map = config('nova-admin.ads_protocol.position_map');

        $this->assertSame('自定义首页横幅', $positions['home_banner1']);
        $this->assertSame('专属广告位', $positions['custom_spot']);
        $this->assertSame('custom_spot', $map['custom_spot']);

        // 宿主没写的包默认值依然存在
        $this->assertArrayHasKey('interstitial', $positions);
        $this->assertArrayHasKey('home_banner_2', $map);
        $this->assertSame(1, config('nova-admin.ads_protocol.version'));
    }

    public function test_false_removes_a_package_key(): void
    {
        $this->mergeHostConfig([
            'ad_positions' => ['interstitial' => false],
            'ads_protocol' => ['position_map' => ['interstitial' => false]],
        ]);

        $this->assertArrayNotHasKey('interstitial', config('nova-admin.ad_positions'));
        $this->assertArrayNotHasKey('interstitial', config('nova-admin.ads_protocol.position_map'));
        $this->assertArrayHasKey('anchor', config('nova-admin.ad_positions'));
    }

    public function test_false_on_a_boolean_key_is_a_plain_override(): void
    {
        $this->mergeHostConfig([
            'sitemap' => ['enabled' => false],
        ]);

        $this->assertFalse(config('nova-admin.sitemap.enabled'));
    }

    public function test_lists_are_replaced_as_a_whole(): void
    {
        $this->mergeHostConfig([
            'site_settings' => ['favicon' => ['accepted_types' => ['image/png']]],
            'sitemap'       => ['urls' => [['loc' => '/custom', 'changefreq' => 'weekly', 'priority' => '0.9']]],
        ]);

        $this->assertSame(['image/png'], config('nova-admin.site_settings.favicon.accepted_types'));
        $this->assertSame('/custom', config('nova-admin.sitemap.urls.0.loc'));
        $this->assertCount(1, config('nova-admin.sitemap.urls'));
    }

    public function test_empty_array_keeps_package_defaults(): void
    {
        // 差异模板里留空的追加位
        $this->mergeHostConfig([
            'ad_positions' => [],
            'ads_protocol' => ['position_map' => []],
        ]);

        $this->assertArrayHasKey('global_head', config('nova-admin.ad_positions'));
        $this->assertArrayHasKey('global_head', config('nova-admin.ads_protocol.position_map'));
    }

    public function test_every_shipped_protocol_target_is_an_enabled_position(): void
    {
        $positions = config('nova-admin.ad_positions');

        foreach (config('nova-admin.ads_protocol.position_map') as $key => $target) {
            $this->assertArrayHasKey($target, $positions, "协议键 {$key} 映射的广告位 {$target} 未在 ad_positions 中启用");
        }
    }

    public function test_site_settings_fields_have_defaults(): void
    {
        foreach ([
            'site_name', 'subtitle', 'copyright', 'contact_email', 'meta_title_template',
            'meta_description', 'meta_keywords', 'favicon_path', 'logo_path',
        ] as $key) {
            $this->assertArrayHasKey($key, config('nova-admin.site_defaults'));
        }
    }
}
