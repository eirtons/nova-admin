<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class DoctorCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    public function test_it_passes_on_the_shipped_config(): void
    {
        $this->artisan('nova-admin:doctor')->assertExitCode(0);
    }

    public function test_it_fails_when_a_mapped_position_was_deleted_from_ad_positions(): void
    {
        // 站点裁剪 ad_positions 却没同步 position_map，正是平台下发失败的成因
        config()->set('nova-admin.ad_positions', ['global_head' => '全局 Head']);

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('未在 ad_positions 中启用')
            ->assertExitCode(1);
    }

    protected function withViews(array $views): void
    {
        $dir = sys_get_temp_dir().'/nova-doctor-'.uniqid();
        foreach ($views as $name => $contents) {
            File::ensureDirectoryExists(dirname($dir.'/'.$name));
            File::put($dir.'/'.$name, $contents);
        }

        config()->set('view.paths', [$dir]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
    }

    public function test_half_paired_content_position_fails(): void
    {
        $this->withViews(['home.blade.php' => '<x-ad-head position="home_banner1" />']);

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('home_banner1（缺 <x-ad-body>）')
            ->assertExitCode(1);
    }

    public function test_missing_content_positions_only_warn_unless_strict(): void
    {
        $this->withViews(['home.blade.php' => '<x-ad-head position="home_banner1" /><x-ad-body position="home_banner1" />']);

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('没有渲染点')
            ->assertExitCode(0);

        $this->artisan('nova-admin:doctor --strict')->assertExitCode(1);
    }

    public function test_complete_templates_pass_strict(): void
    {
        $tags = '';
        foreach (['home_banner1', 'home_banner2', 'detail_banner1', 'detail_banner2'] as $position) {
            $tags .= "<x-ad-head position=\"{$position}\" />\n<x-ad-body position=\"{$position}\" />\n";
        }
        // 布局级位与 global_head 由布局组件输出，不要求出现在模板里
        $this->withViews(['layouts/app.blade.php' => '<x-ad-layout-head />', 'home.blade.php' => $tags]);

        $this->artisan('nova-admin:doctor --strict')
            ->expectsOutputToContain('模板广告渲染点完整')
            ->assertExitCode(0);
    }

    public function test_dynamic_position_fails(): void
    {
        $this->withViews(['home.blade.php' => '<x-ad-head :position="$slot" /><x-ad-body :position="$slot" />']);

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('动态 :position')
            ->assertExitCode(1);
    }

    public function test_unknown_position_in_template_fails(): void
    {
        $this->withViews(['home.blade.php' => '<x-ad-head position="ghost" /><x-ad-body position="ghost" />']);

        $this->artisan('nova-admin:doctor')
            ->expectsOutputToContain('引用的广告位 ghost 未在 ad_positions 中启用')
            ->assertExitCode(1);
    }
}
