<?php

namespace Nbutl\NovaAdmin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Nbutl\NovaAdmin\Filament\Pages\AdsTxtPage;
use Nbutl\NovaAdmin\Filament\Pages\Auth\Login;
use Nbutl\NovaAdmin\Filament\Pages\RobotsTxtPage;
use Nbutl\NovaAdmin\Filament\Pages\SiteSettingsPage;
use Nbutl\NovaAdmin\Filament\Pages\SystemLogsPage;
use Nbutl\NovaAdmin\Filament\Resources\AdSpotResource;
use Nbutl\NovaAdmin\Filament\Resources\StaticPageResource;
use Nbutl\NovaAdmin\Http\Middleware\SetAdminLocale;

class NovaAdminPlugin implements Plugin
{
    protected bool $useChineseLocale = true;

    protected bool $useLogin = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'nova-admin';
    }

    public function withoutLogin(): static
    {
        $this->useLogin = false;

        return $this;
    }

    public function withoutChineseLocale(): static
    {
        $this->useChineseLocale = false;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [AdSpotResource::class];

        if (config('nova-admin.static_pages.enabled', true)) {
            $resources[] = StaticPageResource::class;
        }

        $pages = [SiteSettingsPage::class];

        if (config('nova-admin.ads_txt.enabled', true)) {
            $pages[] = AdsTxtPage::class;
        }
        if (config('nova-admin.robots_txt.enabled', true)) {
            $pages[] = RobotsTxtPage::class;
        }
        if (config('nova-admin.logs.enabled', true)) {
            $pages[] = SystemLogsPage::class;
        }

        $panel->resources($resources)->pages($pages);

        // 布局：侧边栏收窄（默认 20rem 偏宽）；内容区宽度保持 Filament 默认，
        // 需要大空间的页面（如系统日志）自行覆盖 $maxContentWidth
        $panel->sidebarWidth(config('nova-admin.layout.sidebar_width', '16rem'));

        if ($maxWidth = config('nova-admin.layout.max_content_width')) {
            $panel->maxContentWidth($maxWidth);
        }

        if ($this->useLogin) {
            $panel->login(Login::class);
        }

        if ($this->useChineseLocale) {
            $panel->middleware([SetAdminLocale::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        $this->registerLogoLink();
    }

    /**
     * 让后台品牌 Logo 点击跳前台首页（可新标签页打开）。
     */
    protected function registerLogoLink(): void
    {
        $conf = config('nova-admin.admin_brand');

        if (empty($conf['logo_link_to_front'])) {
            return;
        }

        $url = $conf['front_url'] ?? '/';
        $newTab = ! empty($conf['new_tab']);

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString($this->logoLinkScript($url, $newTab))
        );
    }

    protected function logoLinkScript(string $url, bool $newTab): string
    {
        $url = e($url);
        $targetJs = $newTab
            ? "el.setAttribute('target', '_blank'); el.setAttribute('rel', 'noopener noreferrer');"
            : "el.removeAttribute('target'); el.removeAttribute('rel');";

        return <<<HTML
            <script>
            (function () {
                function bindLogoLink() {
                    // Filament v5：品牌为 .fi-logo（div），外层 <a> 才是链接（顶栏或侧边栏头部）
                    var logo = document.querySelector('.fi-logo');
                    var el = logo ? logo.closest('a') : null;
                    if (! el) {
                        el = document.querySelector('.fi-topbar-start a')
                            || document.querySelector('.fi-sidebar-header a');
                    }
                    if (el) {
                        el.setAttribute('href', '{$url}');
                        {$targetJs}
                    }
                }
                document.addEventListener('DOMContentLoaded', bindLogoLink);
                document.addEventListener('livewire:navigated', bindLogoLink);
            })();
            </script>
            HTML;
    }
}
