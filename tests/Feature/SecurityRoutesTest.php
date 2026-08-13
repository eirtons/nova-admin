<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class SecurityRoutesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    public function test_package_does_not_register_a_passwordless_login_route(): void
    {
        $this->assertFalse($this->app['router']->has('nova-admin.quick-login'));
        $this->get('/quick-login')->assertNotFound();
    }
}
