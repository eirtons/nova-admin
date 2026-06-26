<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\Database\Seeders\NovaAdminSeeder;
use Inova\NovaAdmin\Models\AdSpot;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'nova-admin:install {--force : 重置默认管理员密码}';

    protected $description = '安装 nova-admin：创建并接入后台 Panel、执行迁移、生成默认管理员与初始化数据';

    public function handle(): int
    {
        $this->info('开始安装 nova-admin...');

        // 新项目尚无 Panel 时，自动创建默认 admin Panel
        if (! $this->ensurePanelExists()) {
            return self::FAILURE;
        }

        // 配置（迁移由包直接加载，无需发布到项目）
        // mergeConfigFrom 已加载包默认值，宿主项目仅在需要自定义时才发布配置
        if (! file_exists(config_path('nova-admin.php'))) {
            $this->info('未发布 config/nova-admin.php，使用包默认配置。如需自定义请运行：php artisan vendor:publish --tag=nova-admin-config');
        }

        // 将插件接到 Filament Panel 配置链尾，确保覆盖 Filament 默认登录页
        if (! $this->registerPanelPlugin()) {
            return self::FAILURE;
        }

        // 确保默认用户模型允许访问 Filament Panel，避免生产后台登录后 403
        $this->ensureFilamentUserAccess();

        // 发布后台静态资源，避免 Web 服务器静态规则拦截 Livewire 动态脚本路由
        $this->publishFrontendAssets();

        // 执行项目全部待运行迁移，确保 users 与包表均已创建
        $this->call('migrate', ['--force' => true]);

        // 数据初始化（管理员、robots.txt、站点默认值、静态页面）
        if (! $this->seedNovaAdminData()) {
            return self::FAILURE;
        }

        // 仅为空表填充测试广告，避免重复安装覆盖已有数据
        $adModel = AdSpot::class;
        if ($adModel::query()->doesntExist()) {
            $this->call('ad:seed');
        } else {
            $this->info('广告数据已存在，跳过测试广告填充。');
        }

        // 把默认时区落到宿主 config/app.php 与 .env，默认 Asia/Shanghai
        $this->ensureTimezoneDefault();

        // 忽略后台生成的公开文本文件，并取消跟踪 Laravel 默认 robots.txt
        $this->ignoreGeneratedPublicFiles();

        // 将 NovaAdminSeeder 注册到宿主项目 DatabaseSeeder
        $this->registerSeeder();

        // storage 软链（站点设置上传的 Favicon / Logo 经 /storage 访问）
        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        // 完成
        $this->newLine();
        $this->info('安装完成。nova-admin 已接入 Filament Panel。');

        return self::SUCCESS;
    }

    protected function ensureFilamentUserAccess(): void
    {
        if ($this->userModelClass() !== 'App\\Models\\User') {
            $this->warn('用户模型不是 App\\Models\\User，已跳过自动接入 FilamentUser。');

            return;
        }

        $path = $this->userModelPath();
        if (! file_exists($path)) {
            $this->warn('未找到 app/Models/User.php，已跳过自动接入 FilamentUser。');

            return;
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            $this->warn('无法读取 User 模型，请手动实现 FilamentUser。');

            return;
        }

        if (str_contains($contents, 'FilamentUser') && str_contains($contents, 'canAccessPanel')) {
            return;
        }

        $updated = $this->ensureUseStatement($contents, 'Filament\\Models\\Contracts\\FilamentUser');
        $updated = $this->ensureUseStatement($updated, 'Filament\\Panel');

        if (! str_contains($updated, 'implements FilamentUser')) {
            // 已有 implements：追加到列表末尾；否则给 class 加上 implements 子句
            $updated = preg_replace(
                '/class\s+User\s+extends\s+Authenticatable\s+implements\s+([^{\r\n]+)/',
                'class User extends Authenticatable implements $1, FilamentUser',
                $updated,
                1,
                $count,
            );

            if ($count !== 1) {
                $updated = preg_replace(
                    '/class\s+User\s+extends\s+Authenticatable/',
                    'class User extends Authenticatable implements FilamentUser',
                    $updated,
                    1,
                    $count,
                );
            }

            if ($count !== 1) {
                $this->warn('无法自动修改 User 模型，请手动实现 FilamentUser。');

                return;
            }
        }

        if (! str_contains($updated, 'canAccessPanel')) {
            $method = <<<'PHP'

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

PHP;
            $updated = preg_replace(
                '/(class\s+User[^{]*\{\s*)/',
                "$1{$method}",
                $updated,
                1,
                $count,
            );

            if ($count !== 1) {
                $this->warn('无法自动添加 canAccessPanel 方法，请手动实现 FilamentUser。');

                return;
            }
        }

        file_put_contents($path, $updated);
        $this->info('已将 App\\Models\\User 接入 FilamentUser，避免后台登录后 403。');
    }

    protected function ensureUseStatement(string $contents, string $use): string
    {
        $statement = "use {$use};";
        if (str_contains($contents, $statement)) {
            return $contents;
        }

        return preg_replace(
            '/(namespace\s+[^;]+;\s*)/',
            "$1\n{$statement}\n",
            $contents,
            1,
        ) ?? $contents;
    }

    protected function userModelClass(): string
    {
        return (string) config('auth.providers.users.model', 'App\\Models\\User');
    }

    protected function userModelPath(): string
    {
        return app_path('Models/User.php');
    }

    protected function publishFrontendAssets(): void
    {
        $this->call('filament:assets');
        $this->call('livewire:publish', ['--assets' => true]);
    }

    protected function seedNovaAdminData(): bool
    {
        try {
            $seeder = $this->makeNovaAdminSeeder();
            if ($this->laravel !== null) {
                $seeder->setContainer($this->laravel);
            }
            $seeder->setCommand($this);
            $seeder->force = (bool) $this->option('force');
            $seeder->run();

            return true;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }
    }

    protected function makeNovaAdminSeeder(): NovaAdminSeeder
    {
        return new NovaAdminSeeder();
    }

    /**
     * 默认时区落到宿主自有文件，宿主可见可改、复制 .env 部署即默认上海。
     * - config/app.php：出厂硬编码 'UTC' 换成 env('APP_TIMEZONE', 'Asia/Shanghai')
     * - .env / .env.example：缺 APP_TIMEZONE 行则补 Asia/Shanghai；已有则尊重不动
     */
    protected function ensureTimezoneDefault(): void
    {
        $this->patchAppTimezoneConfig();

        foreach (['.env', '.env.example'] as $file) {
            $this->ensureEnvTimezone(base_path($file));
        }
    }

    protected function patchAppTimezoneConfig(): void
    {
        $path = config_path('app.php');
        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);

        // 出厂硬编码 'timezone' => 'UTC'，换成读 env、默认上海
        $patched = preg_replace(
            "/'timezone'\\s*=>\\s*'UTC'\\s*,/",
            "'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),",
            $contents,
            1,
            $count,
        );

        if ($count > 0 && $patched !== null) {
            File::put($path, $patched);
            $this->info("已将 config/app.php 默认时区改为 env('APP_TIMEZONE', 'Asia/Shanghai')。");
        }
    }

    protected function ensureEnvTimezone(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);

        // 已有 APP_TIMEZONE（含注释掉的）则尊重宿主，不改
        if (preg_match('/^\s*#?\s*APP_TIMEZONE\s*=/m', $contents)) {
            return;
        }

        $line = 'APP_TIMEZONE=Asia/Shanghai';

        // 插到 APP_FAKER_LOCALE 行后，和其他 APP_* 放一起；缺该行才追加到末尾
        $patched = preg_replace(
            '/^(APP_FAKER_LOCALE=.*)$/m',
            '$1'.PHP_EOL.$line,
            $contents,
            1,
            $count,
        );

        $contents = ($count > 0 && $patched !== null)
            ? $patched
            : rtrim($contents).PHP_EOL.$line.PHP_EOL;

        File::put($path, $contents);
        $this->info('已在 '.basename($path).' 写入 '.$line.'。');
    }

    protected function ignoreGeneratedPublicFiles(): void
    {
        $gitignorePath = base_path('.gitignore');
        $contents = File::exists($gitignorePath) ? File::get($gitignorePath) : '';
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $entries = ['/public/robots.txt', '/public/ads.txt', '/public/vendor/livewire', '/public/js/filament', '/public/css/filament', '/public/fonts/filament'];
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
                    ."\n            ->plugin(\\Inova\\NovaAdmin\\NovaAdminPlugin::make())"
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

    protected function registerSeeder(): void
    {
        $seederFile = database_path('seeders/DatabaseSeeder.php');

        if (! File::exists($seederFile)) {
            return;
        }

        $contents = File::get($seederFile);

        if (str_contains($contents, 'NovaAdminSeeder')) {
            return;
        }

        $useStatement = 'use Inova\\NovaAdmin\\Database\\Seeders\\NovaAdminSeeder;';
        $callStatement = '        $this->call(NovaAdminSeeder::class);';

        if (! str_contains($contents, $useStatement)) {
            $contents = preg_replace(
                '/(namespace\s+[^;]+;\s*)/',
                "$1\n{$useStatement}\n",
                $contents,
                1,
            );
        }

        $contents = preg_replace(
            '/(public\s+function\s+run\s*\(\s*\)\s*:\s*void\s*\{)/',
            "$1\n{$callStatement}",
            $contents,
            1,
        );

        File::put($seederFile, $contents);
        $this->info('已将 NovaAdminSeeder 注册到 DatabaseSeeder。');
    }
}
