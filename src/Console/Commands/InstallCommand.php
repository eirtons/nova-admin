<?php

namespace Nbutl\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nbutl\NovaAdmin\Database\Seeders\AdminUserSeeder;
use Nbutl\NovaAdmin\Models\AdSpot;
use Nbutl\NovaAdmin\Models\StaticPage;
use Nbutl\NovaAdmin\Services\PublicTextFileService;
use Nbutl\NovaAdmin\Services\SiteConfigService;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'nova-admin:install {--force : 重置默认管理员密码}';

    protected $description = '安装 nova-admin：创建并接入后台 Panel、执行迁移、生成默认管理员与初始化数据';

    public function handle(): int
    {
        $this->info('开始安装 nova-admin...');

        // 1. 新项目尚无 Panel 时，自动创建默认 admin Panel
        if (! $this->ensurePanelExists()) {
            return self::FAILURE;
        }

        // 2. 配置（迁移由包直接加载，无需发布到项目）
        // mergeConfigFrom 已加载包默认值，宿主项目仅在需要自定义时才发布配置
        if (! file_exists(config_path('nova-admin.php'))) {
            $this->info('未发布 config/nova-admin.php，使用包默认配置。如需自定义请运行：php artisan vendor:publish --tag=nova-admin-config');
        }

        // 3. 将插件接到 Filament Panel 配置链尾，确保覆盖 Filament 默认登录页
        if (! $this->registerPanelPlugin()) {
            return self::FAILURE;
        }

        // 4. 发布后台静态资源，避免 Web 服务器静态规则拦截 Livewire 动态脚本路由
        $this->publishFrontendAssets();

        // 5. 执行项目全部待运行迁移，确保 users 与包表均已创建
        $this->call('migrate', ['--force' => true]);

        // 6. 生成默认管理员
        $seeder = new AdminUserSeeder();
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->force = (bool) $this->option('force');
        $seeder->run();

        // 7. 仅为空表填充测试广告，避免重复安装覆盖已有数据
        $adModel = AdSpot::class;
        if ($adModel::query()->doesntExist()) {
            $this->call('ad:seed');
        } else {
            $this->info('广告数据已存在，跳过测试广告填充。');
        }

        // 8. 忽略后台生成的公开文本文件，并取消跟踪 Laravel 默认 robots.txt
        $this->ignoreGeneratedPublicFiles();

        // 9. 初始化默认 robots.txt（覆盖 Laravel 自带的占位 public/robots.txt）
        if (app(SiteConfigService::class)->get('robots_txt_content') === null) {
            $svc = app(PublicTextFileService::class);
            $svc->save('robots_txt', $svc->defaultTemplate('robots_txt'));
            $this->info('已写入默认 robots.txt（Sitemap 按 APP_URL 域名生成）');
        }

        // 10. 初始化站点设置默认值（仅写入尚未设置的键）
        $config = app(SiteConfigService::class);
        foreach (config('nova-admin.site_defaults', []) as $key => $value) {
            if ($config->get($key) === null) {
                $config->set($key, $value);
                $this->info("已写入站点设置默认值：{$key} = {$value}");
            }
        }

        // 11. 初始化预置静态页面（仅创建尚不存在的 slug）
        if (config('nova-admin.static_pages.enabled', true)) {
            foreach (config('nova-admin.static_pages.presets', []) as $slug => $title) {
                StaticPage::firstOrCreate(['slug' => $slug], ['title' => $title]);
            }
        }

        // 12. storage 软链（站点设置上传的 Favicon / Logo 经 /storage 访问）
        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        // 13. 完成
        $this->newLine();
        $this->info('安装完成。nova-admin 已接入 Filament Panel。');

        return self::SUCCESS;
    }

    protected function publishFrontendAssets(): void
    {
        $this->call('filament:assets');
        $this->call('livewire:publish', ['--assets' => true]);
    }

    protected function ignoreGeneratedPublicFiles(): void
    {
        $gitignorePath = base_path('.gitignore');
        $contents = File::exists($gitignorePath) ? File::get($gitignorePath) : '';
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $entries = ['/public/robots.txt', '/public/ads.txt', '/public/vendor/livewire'];
        $missingEntries = array_values(array_diff($entries, $lines));

        if ($missingEntries !== []) {
            $contents = rtrim($contents);
            $contents .= ($contents === '' ? '' : PHP_EOL).implode(PHP_EOL, $missingEntries).PHP_EOL;
            File::put($gitignorePath, $contents);
            $this->info('已将公开文本文件和 Livewire 静态资源加入 .gitignore。');
        }

        try {
            $insideWorkTree = new Process(
                ['git', 'rev-parse', '--is-inside-work-tree'],
                base_path(),
            );
            $insideWorkTree->run();

            if (! $insideWorkTree->isSuccessful()) {
                return;
            }

            $untrack = new Process(
                ['git', 'rm', '--cached', '--ignore-unmatch', '--', 'public/robots.txt', 'public/ads.txt'],
                base_path(),
            );
            $untrack->run();

            if ($untrack->isSuccessful()) {
                $this->info('已取消 Git 对 public/robots.txt、public/ads.txt 的跟踪。');
            } else {
                $this->warn('无法自动取消公开文本文件的 Git 跟踪，请检查 Git 工作区状态。');
            }
        } catch (\Throwable) {
            $this->warn('未检测到可用的 Git，已跳过取消跟踪操作。');
        }
    }

    protected function ensurePanelExists(): bool
    {
        $providerFiles = glob(app_path('Providers/Filament/*PanelProvider.php')) ?: [];

        if ($providerFiles !== []) {
            return true;
        }

        $panelId = (string) config('nova-admin.panel.id', 'admin');
        $this->info("未检测到 Filament Panel，正在创建 {$panelId} Panel...");

        return $this->createPanel($panelId);
    }

    protected function createPanel(string $panelId): bool
    {
        if ($this->call('make:filament-panel', [
            'id' => $panelId,
            '--no-interaction' => true,
        ]) !== self::SUCCESS) {
            return false;
        }

        return $this->call('filament:install', [
            '--no-interaction' => true,
        ]) === self::SUCCESS;
    }

    protected function registerPanelPlugin(): bool
    {
        $panelId = (string) config('nova-admin.panel.id', 'admin');
        $providerFiles = glob(app_path('Providers/Filament/*PanelProvider.php')) ?: [];

        foreach ($providerFiles as $providerFile) {
            $contents = File::get($providerFile);

            if (! preg_match('/->id\(\s*[\'"]'.preg_quote($panelId, '/').'[\'"]\s*\)/', $contents)) {
                continue;
            }

            if (str_contains($contents, 'NovaAdminPlugin::make()')) {
                $this->info("Filament Panel [{$panelId}] 已接入 nova-admin。");

                return true;
            }

            $updated = preg_replace_callback(
                '/(return\s+\$panel\b.*?)(;\s*\r?\n\s*})/s',
                fn (array $matches): string => $matches[1]
                    ."\n            ->plugin(\\Nbutl\\NovaAdmin\\NovaAdminPlugin::make())"
                    .$matches[2],
                $contents,
                1,
                $count,
            );

            if ($count === 1 && is_string($updated)) {
                File::put($providerFile, $updated);
                $this->info("已将 nova-admin 接入 Filament Panel [{$panelId}]。");

                return true;
            }
        }

        $this->error(
            "未找到 id={$panelId} 的 Filament PanelProvider。"
            .'请确认 config/nova-admin.php 中的 Panel ID 与项目现有 Panel 一致。'
        );

        return false;
    }
}
