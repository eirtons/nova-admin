<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class IsAdminMigrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    public function test_package_migration_adds_is_admin_to_users(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('password');
        });

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));
    }
}
