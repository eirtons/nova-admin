<?php

namespace Nbutl\NovaAdmin\Database\Seeders;

use Illuminate\Database\Seeder;
use Nbutl\NovaAdmin\Models\StaticPage;
use Nbutl\NovaAdmin\Services\PublicTextFileService;
use Nbutl\NovaAdmin\Services\SiteConfigService;

class NovaAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $this->initRobotsTxt();
        $this->initSiteDefaults();
        $this->initStaticPages();
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
