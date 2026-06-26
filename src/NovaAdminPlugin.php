<?php

namespace Inova\NovaAdmin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Inova\NovaAdmin\Filament\Pages\AdCodeGeneratorPage;
use Inova\NovaAdmin\Filament\Pages\AdsTxtPage;
use Inova\NovaAdmin\Filament\Pages\Auth\Login;
use Inova\NovaAdmin\Filament\Pages\RobotsTxtPage;
use Inova\NovaAdmin\Filament\Pages\SiteSettingsPage;
use Inova\NovaAdmin\Filament\Pages\StaticPagesPage;
use Inova\NovaAdmin\Filament\Pages\SystemLogsPage;
use Inova\NovaAdmin\Filament\Resources\AdSpotResource;
use Inova\NovaAdmin\Http\Middleware\SetAdminLocale;

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

        $pages = [SiteSettingsPage::class, AdCodeGeneratorPage::class];

        if (config('nova-admin.static_pages.enabled', true)) {
            $pages[] = StaticPagesPage::class;
        }
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
        $this->registerCodeHighlight();
        $this->registerCodeEditor();
    }

    /**
     * 后台代码高亮：CDN 引入 highlight.js，提供全局 novaHighlight()，
     * 并在 Livewire 局部刷新后重新高亮（生成结果、查看代码弹窗等只读代码块）。
     */
    protected function registerCodeHighlight(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css">
                <script defer src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
                <script>
                (function () {
                    // 遍历尚未高亮的代码块并应用 highlight.js
                    window.novaHighlight = function () {
                        if (! window.hljs) return;
                        document.querySelectorAll('pre code.nova-hl:not([data-highlighted])').forEach(function (el) {
                            window.hljs.highlightElement(el);
                        });
                    };
                    // 首次加载（highlight.js 异步，load 后再跑一次）
                    document.addEventListener('DOMContentLoaded', window.novaHighlight);
                    window.addEventListener('load', window.novaHighlight);
                    // Livewire 局部刷新后重新高亮
                    document.addEventListener('livewire:navigated', window.novaHighlight);
                    document.addEventListener('livewire:update', function () { setTimeout(window.novaHighlight, 50); });
                })();
                </script>
                HTML)
        );
    }

    /**
     * 暗色代码编辑器：透明 textarea 叠在 highlight.js 暗色高亮层之上（不依赖 CDN 模块，
     * 只用已加载的 highlight.js）。后台保持亮色，编辑器单独暗色。供广告 Head/Body 录入。
     * Alpine 组件 novaCodeEditor 由 alpine:init 注册（Filament 自带 Alpine）。
     */
    protected function registerCodeEditor(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    .nova-ce { position: relative; background: #0d1117; border-radius: 0.5rem; overflow: hidden; }
                    .nova-ce > textarea,
                    .nova-ce > pre {
                        margin: 0; padding: 0.75rem; border: 0;
                        font: 0.8rem/1.5 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                        white-space: pre-wrap; word-break: break-word; overflow-wrap: break-word;
                        tab-size: 2;
                    }
                    .nova-ce > textarea {
                        position: absolute; inset: 0; width: 100%; height: 100%;
                        resize: none; background: transparent; color: transparent;
                        caret-color: #e6edf3; outline: none; z-index: 1; overflow: hidden;
                    }
                    .nova-ce > pre { margin: 0; min-height: 8rem; pointer-events: none; }
                    .nova-ce > pre > code { background: transparent !important; padding: 0; }
                </style>
                <script>
                (function () {
                    function build() {
                        if (! window.Alpine) return;
                        window.Alpine.data('novaCodeEditor', () => ({
                            code: '',
                            init() {
                                // entangle 的初值在 x-init 后可用；首帧同步一次再高亮
                                this.$nextTick(() => this.refresh());
                                this.$watch('code', () => this.refresh());
                            },
                            refresh() {
                                const pre = this.$refs.highlight;
                                if (! pre) return;
                                // 末尾换行补一个空格，避免最后一行高度塌陷导致错位
                                let html = (this.code ?? '');
                                if (window.hljs) {
                                    pre.innerHTML = '<code class="hljs language-xml"></code>';
                                    pre.firstChild.textContent = html;
                                    delete pre.firstChild.dataset.highlighted;
                                    window.hljs.highlightElement(pre.firstChild);
                                } else {
                                    pre.textContent = html;
                                }
                            },
                            // textarea 滚动时同步高亮层（长内容）
                            onScroll() {
                                this.$refs.highlight.scrollTop = this.$refs.input.scrollTop;
                                this.$refs.highlight.scrollLeft = this.$refs.input.scrollLeft;
                            },
                        }));
                    }
                    if (window.Alpine) build();
                    else document.addEventListener('alpine:init', build);
                })();
                </script>
                HTML)
        );
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
