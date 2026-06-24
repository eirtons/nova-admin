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

            public bool $composerScriptsEnsured = false;

            public function call($command, array $arguments = [])
            {
                $this->calls[] = [$command, $arguments];

                return self::SUCCESS;
            }

            protected function ensureComposerScripts(): void
            {
                $this->composerScriptsEnsured = true;
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
        $this->assertTrue($command->composerScriptsEnsured);
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

    public function test_it_appends_composer_scripts_when_hook_is_a_string(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nova-admin-composer-');
        file_put_contents($path, json_encode([
            'scripts' => [
                'post-autoload-dump' => '@php artisan package:discover --ansi',
            ],
        ]));

        $command = new class($path) extends InstallCommand
        {
            public function __construct(private string $path)
            {
                parent::__construct();
            }

            public function ensureComposerScriptsForTest(): void
            {
                $this->ensureComposerScripts();
            }

            protected function composerJsonPath(): string
            {
                return $this->path;
            }

            public function info($string, $verbosity = null): void
            {
                //
            }
        };

        $command->ensureComposerScriptsForTest();

        $json = json_decode(file_get_contents($path), true);

        $this->assertSame([
            '@php artisan package:discover --ansi',
            '@php artisan livewire:publish --assets --quiet',
            '@php artisan storage:link --quiet',
        ], $json['scripts']['post-autoload-dump']);

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
}
