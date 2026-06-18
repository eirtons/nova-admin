<?php

namespace Nbutl\NovaAdmin\Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Nbutl\NovaAdmin\Models\StaticPage;
use Nbutl\NovaAdmin\Services\PublicTextFileService;
use Nbutl\NovaAdmin\Services\SiteConfigService;

class NovaAdminSeeder extends Seeder
{
    /** 是否覆盖已存在管理员的密码（由命令 --force 透传）。 */
    public bool $force = false;

    public function run(): void
    {
        if (! $this->runAdminSeeder()) {
            throw new RuntimeException('默认管理员创建失败。');
        }

        $this->initRobotsTxt();
        $this->initSiteDefaults();
        $this->initStaticPages();
    }

    protected function runAdminSeeder(): bool
    {
        $seeder = new AdminUserSeeder();
        $seeder->force = $this->force;

        if (isset($this->container)) {
            $seeder->setContainer($this->container);
        }
        if (isset($this->command)) {
            $seeder->setCommand($this->command);
        }

        return $seeder->run();
    }

    protected function initRobotsTxt(): void
    {
        if (app(SiteConfigService::class)->get('robots_txt_content') !== null) {
            return;
        }

        $svc = app(PublicTextFileService::class);
        $svc->save('robots_txt', $svc->defaultTemplate('robots_txt'));
        $this->command?->info('已写入默认 robots.txt');
    }

    protected function initSiteDefaults(): void
    {
        $config = app(SiteConfigService::class);

        foreach (config('nova-admin.site_defaults', []) as $key => $value) {
            if ($config->get($key) === null) {
                $config->set($key, $value);
                $this->command?->info("已写入站点设置默认值：{$key} = {$value}");
            }
        }
    }

    protected function initStaticPages(): void
    {
        if (! config('nova-admin.static_pages.enabled', true)) {
            return;
        }

        foreach (config('nova-admin.static_pages.presets', []) as $slug => $title) {
            StaticPage::firstOrCreate(['slug' => $slug], ['title' => $title]);
        }
    }
}
