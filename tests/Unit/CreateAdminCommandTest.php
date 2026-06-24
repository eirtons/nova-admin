<?php

namespace Nova\NovaAdmin\Tests\Unit;

use Nova\NovaAdmin\Console\Commands\CreateAdminCommand;
use Nova\NovaAdmin\Database\Seeders\AdminUserSeeder;
use PHPUnit\Framework\TestCase;

class CreateAdminCommandTest extends TestCase
{
    public function test_it_returns_failure_when_admin_seeder_fails(): void
    {
        $command = new class extends CreateAdminCommand
        {
            public function option($key = null)
            {
                return false;
            }

            protected function makeAdminUserSeeder(): AdminUserSeeder
            {
                return new class extends AdminUserSeeder
                {
                    public function run(): bool
                    {
                        return false;
                    }
                };
            }
        };

        $this->assertSame(CreateAdminCommand::FAILURE, $command->handle());
    }

    public function test_it_passes_force_to_admin_seeder(): void
    {
        $command = new class extends CreateAdminCommand
        {
            public ?AdminUserSeeder $seeder = null;

            public function option($key = null)
            {
                return $key === 'force';
            }

            protected function makeAdminUserSeeder(): AdminUserSeeder
            {
                return $this->seeder = new class extends AdminUserSeeder
                {
                    public bool $receivedForce = false;

                    public function run(): bool
                    {
                        $this->receivedForce = $this->force;

                        return true;
                    }
                };
            }
        };

        $this->assertSame(CreateAdminCommand::SUCCESS, $command->handle());
        $this->assertTrue($command->seeder->receivedForce);
    }
}
