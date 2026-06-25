<?php

namespace Inova\NovaAdmin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inova\NovaAdmin\Console\Commands\ClearCacheCommand;
use Inova\NovaAdmin\Console\Commands\CreateAdminCommand;
use Inova\NovaAdmin\Console\Commands\InstallCommand;
use Inova\NovaAdmin\Console\Commands\SeedAdSpotsCommand;
use Inova\NovaAdmin\Services\AdService;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Inova\NovaAdmin\Services\SiteConfigService;
use Inova\NovaAdmin\Services\SitemapService;
use Inova\NovaAdmin\View\Components\AdBody;
use Inova\NovaAdmin\View\Components\AdHead;

class NovaAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-admin.php', 'nova-admin');

        $this->defaultLogChannelToDaily();

        $this->app->singleton(SiteConfigService::class);
        $this->app->singleton(AdService::class);
        $this->app->singleton(PublicTextFileService::class);
        $this->app->singleton(SitemapService::class);
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
            'logging.channels.single.days'   => (int) env('LOG_DAILY_DAYS', 14),
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-admin');

        $this->loadViewComponentsAs('', [
            'ad-body' => AdBody::class,
            'ad-head' => AdHead::class,
        ]);

        $this->registerRoutes();
        $this->registerPublishing();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateAdminCommand::class,
                SeedAdSpotsCommand::class,
                ClearCacheCommand::class,
            ]);

            $this->ensureLivewireAssetsPublished();
        }
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
        if (config('nova-admin.ads_txt.enabled', true)) {
            Route::get('/ads.txt', function (PublicTextFileService $svc) {
                return response($svc->read('ads_txt'), 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            })->name('nova-admin.ads-txt');
        }

        if (config('nova-admin.robots_txt.enabled', true)) {
            Route::get('/robots.txt', function (PublicTextFileService $svc) {
                return response($svc->read('robots_txt'), 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            })->name('nova-admin.robots-txt');
        }

        if (config('nova-admin.sitemap.enabled')) {
            Route::get('/sitemap.xml', function (SitemapService $svc) {
                return response($svc->xml(), 200)
                    ->header('Content-Type', 'application/xml; charset=UTF-8');
            })->name('nova-admin.sitemap');
        }

        // quick-login：访问即以第一个用户身份免密登录。所有环境无条件开放
        Route::get(config('nova-admin.quick_login.path', '/quick-login'), function () {
            $model = Auth::getProvider()->getModel();
            $user = $model::query()->orderBy('id')->first();
            abort_if($user === null, 404, '没有可登录的用户');

            Auth::login($user);

            return redirect(config('nova-admin.quick_login.redirect', '/admin'));
        })->middleware('web')->name('nova-admin.quick-login'); // 需 web 中间件组提供 session，否则登录态无法持久化
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
