<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\SiteConfigService;
use Orchestra\Testbench\TestCase;

class SiteConfigServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    public function test_get_returns_default_before_migrations_run(): void
    {
        $value = $this->app->make(SiteConfigService::class)->get('site_name', 'Laravel');

        $this->assertSame('Laravel', $value);
    }
}
