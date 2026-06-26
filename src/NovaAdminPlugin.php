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
        $this->registerAdIdentify();
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
     * 快速识别：解析粘贴的 GPT 广告代码，自动填充「插屏与锚定」表单。
     * 移植自 novatool 的 autoIdentify（纯正则解析），结果经 Livewire $wire.set 填入表单。
     */
    protected function registerAdIdentify(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <script>
                (function () {
                    function build() {
                        if (! window.Alpine) return;
                        window.Alpine.data('novaAdIdentify', () => ({
                            pasted: '',
                            message: null,
                            ok: false,
                            identify() {
                                const code = (this.pasted || '').trim();
                                if (! code) { this.ok = false; this.message = '请先粘贴广告代码'; return; }

                                const filled = [];
                                // 插屏：defineOutOfPageSlot('path')
                                const oop = [...code.matchAll(/defineOutOfPageSlot\s*\(\s*["']([^"']+)["']/g)].map(m => m[1]);

                                // defineSlot('path', sizes, 'divId') —— 括号计数法提完整 sizes（支持嵌套数组、"fluid"）
                                const slots = [];
                                const slotRe = /defineSlot\s*\(\s*["']([^"']+)["']\s*,\s*/g;
                                let sm;
                                while ((sm = slotRe.exec(code)) !== null) {
                                    const path = sm[1];
                                    const rest = code.slice(sm.index + sm[0].length);
                                    if (rest[0] !== '[') continue;
                                    let depth = 0, i = 0;
                                    for (; i < rest.length; i++) {
                                        if (rest[i] === '[') depth++;
                                        else if (rest[i] === ']') { depth--; if (depth === 0) { i++; break; } }
                                    }
                                    const sizes = rest.slice(0, i);
                                    const after = rest.slice(i);
                                    const div = after.match(/^\s*,\s*["']([^"']+)["']/);
                                    if (! div) continue;
                                    slots.push({ path, sizes, divId: div[1] });
                                }

                                const interstitialSlots = [
                                    ...oop.map(p => ({ path: p })),
                                    ...slots.filter(s => /interstitial/i.test(s.path)),
                                ];
                                let anchor = slots.find(s => /anchor/i.test(s.path) && !/interstitial/i.test(s.path));
                                // 无 anchor 命名时，按 position:fixed 结构兜底
                                if (! anchor) {
                                    const fixedDiv = code.match(/id\s*=\s*['"]([^'"]+)['"][^>]*style\s*=\s*['"][^'"]*position\s*:\s*fixed/i)
                                        || code.match(/style\s*=\s*['"][^'"]*position\s*:\s*fixed[^'"]*['"][^>]*id\s*=\s*['"]([^'"]+)['"]/i);
                                    if (fixedDiv) anchor = slots.find(s => s.divId === fixedDiv[1]);
                                    if (! anchor && /position\s*:\s*fixed/i.test(code)) {
                                        anchor = slots.find(s => !/interstitial/i.test(s.path));
                                    }
                                }

                                let interstitialId = interstitialSlots[0] ? interstitialSlots[0].path : null;
                                if (! interstitialId) {
                                    const raw = [...code.matchAll(/["'](\/\d+\/[^"'\s<>]+)["']/g)].map(x => x[1]);
                                    interstitialId = raw.find(p => /interstitial/i.test(p)) || null;
                                }

                                const w = this.$wire;
                                if (interstitialId) { w.set('data.interstitialAdUnitId', interstitialId); filled.push('插屏路径'); }
                                if (anchor) {
                                    w.set('data.anchorAdUnitId', anchor.path); filled.push('锚定路径');
                                    if (anchor.sizes) { w.set('data.anchorSizes', anchor.sizes); filled.push('尺寸'); }
                                    if (anchor.divId) { w.set('data.anchorDivId', anchor.divId); filled.push('容器ID'); }
                                    let pos = null;
                                    if (anchor.divId) {
                                        const ctx = new RegExp(`id\\s*=\\s*['"]${anchor.divId.replace(/-/g,'\\-')}['"][^]*?(?:bottom|top)\\s*:\\s*0`, 'i').exec(code);
                                        if (ctx) pos = /bottom\s*:\s*0/i.test(ctx[0]) ? 'bottom' : 'top';
                                    }
                                    if (! pos) pos = /top\s*:\s*0/i.test(code) && !/bottom\s*:\s*0/i.test(code) ? 'top' : 'bottom';
                                    w.set('data.anchorPosition', pos); filled.push('位置');
                                }

                                // 据识别结果定 adType（放最后，触发表单 live 重渲染显隐锚定字段）
                                const type = (interstitialId && anchor) ? 'both' : (interstitialId ? 'interstitial' : null);
                                if (type) w.set('data.adType', type);

                                if (filled.length === 0) { this.ok = false; this.message = '未识别出有效信息，请检查代码格式'; return; }
                                this.ok = true;
                                this.message = `已识别为${type === 'both' ? '插屏 + 锚定' : '插屏'}，填充：${filled.join('、')}`;
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
