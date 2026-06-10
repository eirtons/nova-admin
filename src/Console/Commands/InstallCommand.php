<?php

namespace Nbutl\NovaSiteCore\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaSiteCore\Database\Seeders\AdminUserSeeder;
use Nbutl\NovaSiteCore\Services\PublicTextFileService;
use Nbutl\NovaSiteCore\Services\SiteConfigService;

class InstallCommand extends Command
{
    protected $signature = 'nova-site-core:install {--force : 重置默认管理员密码}';

    protected $description = '安装 nova-site-core：检查配置、生成默认管理员、初始化默认数据';

    public function handle(): int
    {
        $this->info('开始安装 nova-site-core...');

        // 1. 检查配置是否已发布
        if (! file_exists(config_path('nova-site-core.php'))) {
            $this->warn('未检测到 config/nova-site-core.php，建议先执行：');
            $this->line('  php artisan vendor:publish --tag=nova-site-core-config');
        }

        // 2. 提示 migrate
        if (! $this->confirm('请确认已执行 migrate（site_configs / ad_spots 表存在）。继续？', true)) {
            $this->line('  php artisan vendor:publish --tag=nova-site-core-migrations');
            $this->line('  php artisan migrate');

            return self::SUCCESS;
        }

        // 3. 生成默认管理员
        $seeder = new AdminUserSeeder();
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->force = (bool) $this->option('force');
        $seeder->run();

        // 4. 初始化默认站点配置
        $this->call('ad:seed');

        // 5. 初始化默认 robots.txt（覆盖 Laravel 自带的占位 public/robots.txt）
        if (app(SiteConfigService::class)->get('robots_txt_content') === null) {
            $svc = app(PublicTextFileService::class);
            $svc->save('robots_txt', $svc->defaultTemplate('robots_txt'));
            $this->info('已写入默认 robots.txt（Sitemap 按 APP_URL 域名生成）');
        }

        // 6. 初始化站点设置默认值（仅写入尚未设置的键）
        $config = app(SiteConfigService::class);
        foreach (config('nova-site-core.site_defaults', []) as $key => $value) {
            if ($config->get($key) === null) {
                $config->set($key, $value);
                $this->info("已写入站点设置默认值：{$key} = {$value}");
            }
        }

        // 7. storage 软链（站点设置上传的 Favicon / Logo 经 /storage 访问）
        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        // 8. 后续接入说明
        $this->newLine();
        $this->info('安装完成。请在 AdminPanelProvider 中接入：');
        $this->line('  ->plugin(\Nbutl\NovaSiteCore\NovaSiteCorePlugin::make())');

        return self::SUCCESS;
    }
}
