<?php

namespace Inova\NovaAdmin\Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Inova\NovaAdmin\Services\SiteConfigService;

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

        $config = app(SiteConfigService::class);
        $replacements = [
            '{{site_name}}'        => $config->get('site_name') ?: 'this website',
            '{{site_description}}' => config('nova-admin.static_pages.site_description', 'an online service'),
            '{{contact_email}}'    => $config->get('contact_email', config('nova-admin.site_defaults.contact_email', '')),
        ];

        foreach (config('nova-admin.static_pages.presets', []) as $slug => $p) {
            // 仅新建时预置合规模板初稿；已存在记录不覆盖用户已编辑内容
            StaticPage::firstOrCreate(['slug' => $slug], [
                'title'   => $p[0],
                'content' => $this->staticPageTemplate($slug, $replacements),
            ]);
        }
    }

    /**
     * 读取合规模板并替换占位符；无模板时返回 null（页面留空待编辑）。
     * 宿主项目 resources/defaults/static-pages/{slug}.html 优先，便于覆盖包内默认。
     */
    protected function staticPageTemplate(string $slug, array $replacements): ?string
    {
        $candidates = [
            resource_path('defaults/static-pages/'.$slug.'.html'),
            __DIR__.'/../../resources/defaults/static-pages/'.$slug.'.html',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return strtr((string) file_get_contents($path), $replacements);
            }
        }

        return null;
    }
}
