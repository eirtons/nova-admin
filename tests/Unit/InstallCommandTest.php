<?php

namespace Inova\NovaAdmin\Tests\Unit;

use Inova\NovaAdmin\Console\Commands\InstallCommand;
use Inova\NovaAdmin\Database\Seeders\NovaAdminSeeder;
use PHPUnit\Framework\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_it_publishes_filament_and_livewire_assets(): void
    {
        $command = new class extends InstallCommand
        {
            public array $calls = [];

            public function call($command, array $arguments = [])
            {
                $this->calls[] = [$command, $arguments];

                return self::SUCCESS;
            }

            public function publishFrontendAssetsForTest(): void
            {
                $this->publishFrontendAssets();
            }
        };

        $command->publishFrontendAssetsForTest();

        $this->assertSame([
            ['filament:assets', []],
            ['livewire:publish', ['--assets' => true]],
        ], $command->calls);
    }

    public function test_it_creates_the_configured_panel_without_interaction(): void
    {
        $command = new class extends InstallCommand
        {
            public array $calls = [];

            public function call($command, array $arguments = [])
            {
                $this->calls[] = [$command, $arguments];

                return self::SUCCESS;
            }

            public function createPanelForTest(string $panelId): bool
            {
                return $this->createPanel($panelId);
            }
        };

        $this->assertTrue($command->createPanelForTest('admin'));
        $this->assertSame([
            ['make:filament-panel', [
                'id' => 'admin',
                '--no-interaction' => true,
            ]],
            ['filament:install', [
                '--no-interaction' => true,
            ]],
        ], $command->calls);
    }

    public function test_it_patches_the_default_user_model_for_filament_access(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nova-admin-user-');
        file_put_contents($path, <<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
}
PHP);

        $command = new class($path) extends InstallCommand
        {
            public function __construct(private string $path)
            {
                parent::__construct();
            }

            public function ensureFilamentUserAccessForTest(): void
            {
                $this->ensureFilamentUserAccess();
            }

            protected function userModelClass(): string
            {
                return 'App\\Models\\User';
            }

            protected function userModelPath(): string
            {
                return $this->path;
            }

            public function info($string, $verbosity = null): void
            {
                //
            }
        };

        $command->ensureFilamentUserAccessForTest();

        $this->assertSame(<<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Inova\NovaAdmin\Concerns\HasNovaAdminAccess;
use Filament\Models\Contracts\FilamentUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasNovaAdminAccess;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
}
PHP, file_get_contents($path));

        // 再跑一次不重复修改
        $command->ensureFilamentUserAccessForTest();
        $this->assertSame(1, substr_count(file_get_contents($path), 'use HasNovaAdminAccess;'));

        @unlink($path);
    }

    public function test_it_passes_force_to_nova_admin_seeder(): void
    {
        $command = new class extends InstallCommand
        {
            public ?NovaAdminSeeder $seeder = null;

            public function option($key = null)
            {
                return $key === 'force';
            }

            public function seedNovaAdminDataForTest(): bool
            {
                return $this->seedNovaAdminData();
            }

            protected function makeNovaAdminSeeder(): NovaAdminSeeder
            {
                return $this->seeder = new class extends NovaAdminSeeder
                {
                    public ?bool $receivedForce = null;

                    public function run(): void
                    {
                        $this->receivedForce = $this->force;
                    }
                };
            }
        };

        $this->assertTrue($command->seedNovaAdminDataForTest());
        $this->assertTrue($command->seeder->receivedForce);
    }

    public function test_it_writes_the_diff_config_stub_once(): void
    {
        $target = sys_get_temp_dir().'/nova-admin-stub-'.uniqid().'/nova-admin.php';

        $command = new class($target) extends InstallCommand
        {
            public function __construct(public string $target)
            {
                parent::__construct();
            }

            public function info($string, $verbosity = null) {}

            protected function configStubTarget(): string
            {
                return $this->target;
            }

            public function writeConfigStubForTest(): void
            {
                $this->writeConfigStub();
            }
        };

        $command->writeConfigStubForTest();
        $stub = require $target;
        $this->assertSame([], $stub['ad_positions']);

        file_put_contents($target, '<?php return [\'locale\' => \'en\'];');
        $command->writeConfigStubForTest();
        $this->assertSame(['locale' => 'en'], require $target);

        @unlink($target);
        @rmdir(dirname($target));
    }
}
