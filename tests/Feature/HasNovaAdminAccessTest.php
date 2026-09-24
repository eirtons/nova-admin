<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Inova\NovaAdmin\Concerns\HasNovaAdminAccess;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class HasNovaAdminAccessTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function user(bool $isAdmin): Authenticatable
    {
        $user = new class extends Authenticatable
        {
            use HasNovaAdminAccess;
        };
        $user->is_admin = $isAdmin;

        return $user;
    }

    public function test_only_admins_can_access_the_nova_panel(): void
    {
        $panel = Panel::make()->id('admin');

        $this->assertTrue($this->user(true)->canAccessPanel($panel));
        $this->assertFalse($this->user(false)->canAccessPanel($panel));
    }

    public function test_other_panels_are_denied(): void
    {
        $this->assertFalse($this->user(true)->canAccessPanel(Panel::make()->id('partner')));
    }
}
