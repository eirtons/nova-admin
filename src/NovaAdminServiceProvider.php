<?php

namespace Nbutl\NovaAdmin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nbutl\NovaAdmin\Console\Commands\ClearCacheCommand;
use Nbutl\NovaAdmin\Console\Commands\CreateAdminCommand;
use Nbutl\NovaAdmin\Console\Commands\InstallCommand;
use Nbutl\NovaAdmin\Console\Commands\SeedAdSpotsCommand;
use Nbutl\NovaAdmin\Services\AdService;
use Nbutl\NovaAdmin\Services\PublicTextFileService;
use Nbutl\NovaAdmin\Services\SiteConfigService;
use Nbutl\NovaAdmin\Services\SitemapService;
use Nbutl\NovaAdmin\View\Components\Ad;
use Nbutl\NovaAdmin\View\Components\AdHead;

class NovaAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-admin.php', 'nova-admin');

        $this->app->singleton(SiteConfigService::class);
        $this->app->singleton(AdService::class);
        $this->app->singleton(PublicTextFileService::class);
        $this->app->singleton(SitemapService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-admin');

        $this->loadViewComponentsAs('nova-admin', [
            'ad'      => Ad::class,
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
        }
    }

    protected function registerRoutes(): void
    {
        if (config('nova-admin.ads_txt.route_fallback')) {
            Route::get('/ads.txt', function (PublicTextFileService $svc) {
                return response($svc->read('ads_txt'), 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            })->name('nova-admin.ads-txt');
        }

        if (config('nova-admin.robots_txt.route_fallback')) {
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

        if ($this->app->environment('local')) {
            Route::get(config('nova-admin.quick_login.path', '/quick-login'), function () {
                $model = Auth::getProvider()->getModel();
                $user = $model::query()->orderBy('id')->first();
                abort_if($user === null, 404, '没有可登录的用户');

                Auth::login($user);

                return redirect(config('nova-admin.quick_login.redirect', '/admin'));
            })->middleware('web')->name('nova-admin.quick-login'); // 需 web 中间件组提供 session，否则登录态无法持久化
        }
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
