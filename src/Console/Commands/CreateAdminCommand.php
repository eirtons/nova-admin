<?php

namespace Nbutl\NovaSiteCore\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaSiteCore\Database\Seeders\AdminUserSeeder;

class CreateAdminCommand extends Command
{
    protected $signature = 'nova-site-core:create-admin {--force : 覆盖已存在账号的密码与基础信息}';

    protected $description = '创建默认管理员（内部调用 AdminUserSeeder）';

    public function handle(): int
    {
        $seeder = new AdminUserSeeder();
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->force = (bool) $this->option('force');
        $seeder->run();

        return self::SUCCESS;
    }
}
