<?php

namespace Inova\NovaAdmin\Tests\Feature;

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
}
