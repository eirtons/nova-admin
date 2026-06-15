<?php

namespace Nbutl\NovaAdmin\Tests\Unit;

use Nbutl\NovaAdmin\Console\Commands\InstallCommand;
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
}
