<?php

namespace Nova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Nova\NovaAdmin\Database\Seeders\AdminUserSeeder;

class CreateAdminCommand extends Command
{
    protected $signature = 'nova-admin:create-admin {--force : 覆盖已存在账号的密码与基础信息}';

    protected $description = '创建默认管理员（内部调用 AdminUserSeeder）';

    public function handle(): int
    {
        $seeder = $this->makeAdminUserSeeder();
        if ($this->laravel !== null) {
            $seeder->setContainer($this->laravel);
        }
        $seeder->setCommand($this);
        $seeder->force = (bool) $this->option('force');

        return $seeder->run() ? self::SUCCESS : self::FAILURE;
    }

    protected function makeAdminUserSeeder(): AdminUserSeeder
    {
        return new AdminUserSeeder();
    }
}
