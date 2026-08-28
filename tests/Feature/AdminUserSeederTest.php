<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inova\NovaAdmin\Database\Seeders\AdminUserSeeder;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

/** Laravel 默认的 User 只把 name/email/password 列入 $fillable，is_admin 不在其中。 */
class RestrictedFillableUser extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_flag_is_written_even_when_not_fillable(): void
    {
        config()->set('auth.providers.users.model', RestrictedFillableUser::class);

        $this->assertTrue((new AdminUserSeeder)->run());

        $this->assertDatabaseHas('users', [
            'email' => config('nova-admin.admin.default_email'),
            'is_admin' => true,
        ]);
    }
}
