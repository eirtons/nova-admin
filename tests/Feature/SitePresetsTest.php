<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\SitePresets\SitePresetProvider;
use Inova\NovaAdmin\SitePresets\SitePresetService;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

class SitePresetsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function tearDown(): void
    {
        File::delete(base_path(SitePresetService::FILE));

        parent::tearDown();
    }

    private function register(?string $initial = null): void
    {
        config()->set('nova-admin.site_presets', [
            'vertical' => ['label' => '垂直领域', 'provider' => FakeVerticalProvider::class, 'initial' => $initial],
        ]);
    }

    public function test_manifest_lists_options_and_drops_new_slugs_outside_the_options(): void
    {
        $this->register();

        $this->assertSame([
            'vertical' => [
                'label' => '垂直领域',
                'options' => ['tech' => 'Technology', 'pets' => 'Pets'],
                'new' => ['pets'],
            ],
        ], app(SitePresetService::class)->manifest());
    }

    public function test_initial_returns_null_when_nothing_was_chosen(): void
    {
        $this->register('');

        $this->assertNull(site_preset('vertical'));
    }

    public function test_initial_returns_the_chosen_slug(): void
    {
        $this->register('pets');

        $this->assertSame('pets', site_preset('vertical'));
    }

    public function test_initial_rejects_a_slug_outside_the_options(): void
    {
        // 部署选了项目不认识的值：必须失败，不能悄悄改成随机
        $this->register('crypto');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[crypto] 不在可选清单中');

        site_preset('vertical');
    }

    public function test_initial_rejects_an_unregistered_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        site_preset('theme');
    }

    public function test_keys_must_be_valid_env_var_suffixes(): void
    {
        config()->set('nova-admin.site_presets', [
            'Vertical-Field' => ['label' => 'x', 'provider' => FakeVerticalProvider::class],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(SitePresetService::class)->definitions();
    }

    public function test_export_writes_the_manifest_and_doctor_accepts_it(): void
    {
        $this->register();

        $this->artisan('nova-admin:export-site-presets')->assertExitCode(0);

        $this->assertSame(app(SitePresetService::class)->encode(), File::get(base_path(SitePresetService::FILE)));
        $this->artisan('nova-admin:doctor')->expectsOutputToContain('建站预设清单与代码一致')->assertExitCode(0);
    }

    public function test_doctor_fails_when_the_file_is_missing(): void
    {
        $this->register();

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('nova-admin:export-site-presets')
            ->assertExitCode(1);
    }

    public function test_doctor_fails_when_the_file_is_stale(): void
    {
        // 新增了领域却没重新导出：webdeploy 下拉里选不到它
        $this->register();
        File::put(base_path(SitePresetService::FILE), "{}\n");

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('不一致')
            ->assertExitCode(1);
    }

    public function test_doctor_fails_on_a_leftover_file_without_registration(): void
    {
        File::put(base_path(SitePresetService::FILE), "{}\n");

        $this->artisan('nova-admin:doctor')->assertExitCode(1);

        $this->artisan('nova-admin:export-site-presets')->assertExitCode(0);
        $this->assertFileDoesNotExist(base_path(SitePresetService::FILE));
    }
}

class FakeVerticalProvider implements SitePresetProvider
{
    public function options(): array
    {
        return ['tech' => 'Technology', 'pets' => 'Pets'];
    }

    public function newOptions(): array
    {
        return ['pets', 'retired'];
    }
}
