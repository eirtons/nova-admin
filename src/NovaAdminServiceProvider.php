<?php

namespace Inova\NovaAdmin;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Inova\NovaAdmin\Http\Middleware\CacheablePage;
use Inova\NovaAdmin\Http\Middleware\SecurityHeaders;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\Console\Commands\ClearCacheCommand;
use Inova\NovaAdmin\Console\Commands\CreateAdminCommand;
use Inova\NovaAdmin\Console\Commands\DoctorCommand;
use Inova\NovaAdmin\Console\Commands\ImportSiteAdConfigCommand;
use Inova\NovaAdmin\Console\Commands\InstallCommand;
use Inova\NovaAdmin\Console\Commands\SeedAdSpotsCommand;
use Inova\NovaAdmin\Services\AdService;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Inova\NovaAdmin\Services\SiteConfigService;
use Inova\NovaAdmin\Services\SitemapService;
use Inova\NovaAdmin\View\Components\AdBody;
use Inova\NovaAdmin\View\Components\AdHead;
use Inova\NovaAdmin\View\Components\AdLayoutBody;
use Inova\NovaAdmin\View\Components\AdLayoutHead;

class NovaAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigRecursively(__DIR__.'/../config/nova-admin.php', 'nova-admin');

        $this->defaultLogChannelToDaily();

        $this->app->singleton(SiteConfigService::class);
        $this->app->singleton(AdService::class);
        $this->app->singleton(PublicTextFileService::class);
        $this->app->singleton(SitemapService::class);
    }

    /**
     * 以包出厂配置为底座，宿主 config/nova-admin.php 只写差异：
     * 包新增的广告位、协议映射等升级后自动继承，不必到各站点同步。
     * 配置缓存里已是合并结果，不再重复合并。
     */
    protected function mergeConfigRecursively(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, static::mergeConfig(require $path, (array) $config->get($key, [])));
    }

    /**
     * 合并规则：
     * - 关联数组逐键递归合并；
     * - 列表（如 sitemap.urls、accepted_types）以宿主为准整体替换，按下标合并会错位；
     * - 宿主写空数组视为「未覆盖」，差异模板里留空的追加位不会清空包默认值；
     * - 宿主写 false 删除该键（如 'interstitial' => false 去掉包内广告位），
     *   但包里本身是布尔值的键（enabled 之类），false 就是普通的关闭。
     */
    public static function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $baseValue = $base[$key] ?? null;

            if ($value === false && ! is_bool($baseValue)) {
                unset($base[$key]);
            } elseif (is_array($value) && is_array($baseValue)) {
                if ($value === []) {
                    continue;
                }

                $base[$key] = array_is_list($value) && array_is_list($baseValue)
                    ? $value
                    : static::mergeConfig($baseValue, $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * 让基座默认按天切割日志：把出厂 single channel（无论被 default 直接用，
     * 还是被 stack 引用，二者都是 Laravel 默认）就地切成 daily。
     * 宿主只要把它换成别的 driver（syslog/外部服务等），就不再命中、不做改动。
     */
    protected function defaultLogChannelToDaily(): void
    {
        if (config('logging.channels.single.driver') !== 'single') {
            return;
        }

        config([
            'logging.channels.single.driver' => 'daily',
            'logging.channels.single.days'   => (int) config('logging.channels.daily.days', 14),
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-admin');

        $this->loadViewComponentsAs('', [
            'ad-body' => AdBody::class,
            'ad-head' => AdHead::class,
            'ad-layout-body' => AdLayoutBody::class,
            'ad-layout-head' => AdLayoutHead::class,
        ]);

        $this->trustProxies();
        $this->registerMiddleware();
        $this->registerViewComposers();

        $this->registerRoutes();
        $this->registerPublishing();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateAdminCommand::class,
                SeedAdSpotsCommand::class,
                ImportSiteAdConfigCommand::class,
                ClearCacheCommand::class,
                DoctorCommand::class,
            ]);

            $this->ensureLivewireAssetsPublished();
        }
    }

    /**
     * 反代（Nginx/CDN）后端跑 HTTP 时，Laravel 看到的 scheme 是 http，
     * 生成的 Livewire update 端点就是 http://，被浏览器按 Mixed Content 拦掉。
     * 等价于宿主 bootstrap/app.php 里的 $middleware->trustProxies(at: '*')，
     * 收进包里，宿主不必每个项目再配一遍。
     * 信任全部代理；只需信任固定 IP 段时由宿主自行覆盖。
     */
    protected function trustProxies(): void
    {
        $trustedProxies = config('nova-admin.trusted_proxies');

        if (filled($trustedProxies)) {
            TrustProxies::at($trustedProxies);
        }
    }

    /**
     * nova.public：宿主前台只读页面的路由组。刻意不含 web 组的会话与 Cookie，
     * 响应才能被 Cloudflare 边缘缓存。宿主 bootstrap/app.php 里用
     * Route::middleware('nova.public')->group(base_path('routes/public.php'))。
     */
    protected function registerMiddleware(): void
    {
        $this->app->make(Router::class)->middlewareGroup('nova.public', [
            SubstituteBindings::class,
            CacheablePage::class,
        ]);

        $this->app->make(HttpKernel::class)->pushMiddleware(SecurityHeaders::class);
    }

    protected function registerViewComposers(): void
    {
        // nova.public 路由没有 ShareErrorsFromSession 注入 $errors，视图里引用 $errors
        // 会直接 500。共享一个空袋子兜底；web 组的请求仍会被覆盖成真实错误。
        View::share('errors', new ViewErrorBag);

        View::composer((array) config('nova-admin.static_pages.footer_views', []), function ($view): void {
            $order = array_keys((array) config('nova-admin.static_pages.presets', []));

            // 后台新增的页排在预置页之后
            $view->with('footerPages', StaticPage::query()
                ->where('is_active', true)
                ->get(['slug', 'title'])
                ->sortBy(fn (StaticPage $page) => array_search($page->slug, $order, true) === false
                    ? PHP_INT_MAX
                    : array_search($page->slug, $order, true))
                ->values());
        });

        // 复用布局的 $section->ads_enabled 开关，任何带该属性的对象都能接入
        View::composer((array) config('nova-admin.ad_disabled_views', []), function ($view): void {
            $view->with('section', (object) ['ads_enabled' => false]);
        });
    }

    protected function ensureLivewireAssetsPublished(): void
    {
        $source = $this->livewireAssetsSourcePath();
        $target = $this->livewireAssetsTargetPath();

        if (! is_file($source.'/manifest.json')) {
            return;
        }

        $sourceManifest = file_get_contents($source.'/manifest.json');
        $targetManifest = is_file($target.'/manifest.json')
            ? file_get_contents($target.'/manifest.json')
            : null;

        if ($sourceManifest === $targetManifest) {
            return;
        }

        File::ensureDirectoryExists($target);
        File::copyDirectory($source, $target);
    }

    protected function livewireAssetsSourcePath(): string
    {
        // Keep this intentionally narrow: nova-admin only mirrors Livewire's
        // versioned dist assets. Filament assets stay on Filament's own command.
        return base_path('vendor/livewire/livewire/dist');
    }

    protected function livewireAssetsTargetPath(): string
    {
        return public_path('vendor/livewire');
    }

    protected function registerRoutes(): void
    {
        $this->registerStaticPageFrontend();

        if (config('nova-admin.ads_txt.enabled', true)) {
            Route::middleware(CacheablePage::class)
                ->get('/ads.txt', function (PublicTextFileService $svc) {
                    return response($svc->read('ads_txt'), 200)
                        ->header('Content-Type', 'text/plain; charset=UTF-8');
                })->name('nova-admin.ads-txt');
        }

        if (config('nova-admin.robots_txt.enabled', true)) {
            Route::middleware(CacheablePage::class)
                ->get('/robots.txt', function (PublicTextFileService $svc) {
                    return response($svc->read('robots_txt'), 200)
                        ->header('Content-Type', 'text/plain; charset=UTF-8');
                })->name('nova-admin.robots-txt');
        }

        if (config('nova-admin.sitemap.enabled')) {
            Route::middleware(CacheablePage::class)
                ->get('/sitemap.xml', function (SitemapService $svc) {
                    return response($svc->xml(), 200)
                        ->header('Content-Type', 'application/xml; charset=UTF-8');
                })->name('nova-admin.sitemap');
        }

    }

    /**
     * 前台静态页：static_pages 表为唯一数据源，后台保存前台即生效。
     * 仅注册 presets 内的 slug，不劫持其他 URL；项目自建静态页路由时置 frontend.enabled=false。
     */
    protected function registerStaticPageFrontend(): void
    {
        if (! config('nova-admin.static_pages.frontend.enabled')) {
            return;
        }

        $slugs = array_keys((array) config('nova-admin.static_pages.presets', []));

        if ($slugs === []) {
            return;
        }

        // 刻意不挂 web 组：静态页是纯展示内容，不需要会话，
        // 带 Set-Cookie 的响应 Cloudflare 一律拒绝缓存。
        // 若项目把该视图换成了含表单的模板，用 static_pages.frontend.cacheable=false 退回 web 组。
        $middleware = config('nova-admin.static_pages.frontend.cacheable', true)
            ? [CacheablePage::class]
            : ['web'];

        Route::middleware($middleware)
            ->get('/{staticPageSlug}', function (string $staticPageSlug) {
                $page = static_page($staticPageSlug);
                abort_if($page === null, 404);

                return view(
                    config('nova-admin.static_pages.frontend.view', 'nova-admin::static-page'),
                    ['page' => $page],
                );
            })
            ->whereIn('staticPageSlug', $slugs)
            ->name(config('nova-admin.static_pages.frontend.route_name', 'pages.show'));

        // 激活的静态页自动进 sitemap
        $this->app->make(SitemapService::class)->register(
            fn () => \Inova\NovaAdmin\Models\StaticPage::query()
                ->where('is_active', true)
                ->whereIn('slug', $slugs)
                ->get()
                ->map(fn ($page) => ['loc' => '/'.$page->slug, 'lastmod' => $page->updated_at]),
        );
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/nova-admin.php' => config_path('nova-admin.php'),
        ], 'nova-admin-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/nova-admin'),
        ], 'nova-admin-views');
    }
}
