<?php

namespace Nbutl\NovaSiteCore;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nbutl\NovaSiteCore\Console\Commands\ClearCacheCommand;
use Nbutl\NovaSiteCore\Console\Commands\CreateAdminCommand;
use Nbutl\NovaSiteCore\Console\Commands\InstallCommand;
use Nbutl\NovaSiteCore\Console\Commands\SeedAdSpotsCommand;
use Nbutl\NovaSiteCore\Services\AdService;
use Nbutl\NovaSiteCore\Services\PublicTextFileService;
use Nbutl\NovaSiteCore\Services\SiteConfigService;
use Nbutl\NovaSiteCore\View\Components\Ad;
use Nbutl\NovaSiteCore\View\Components\AdHead;

class NovaSiteCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-site-core.php', 'nova-site-core');

        $this->app->singleton(SiteConfigService::class);
        $this->app->singleton(AdService::class);
        $this->app->singleton(PublicTextFileService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nova-site-core');

        $this->loadViewComponentsAs('nova-site-core', [
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
        if (config('nova-site-core.ads_txt.route_fallback')) {
            Route::get('/ads.txt', function (PublicTextFileService $svc) {
                return response($svc->read('ads_txt'), 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            })->name('nova-site-core.ads-txt');
        }

        if (config('nova-site-core.robots_txt.route_fallback')) {
            Route::get('/robots.txt', function (PublicTextFileService $svc) {
                return response($svc->read('robots_txt'), 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            })->name('nova-site-core.robots-txt');
        }

        if ($this->app->environment('local')) {
            Route::get(config('nova-site-core.quick_login.path', '/quick-login'), function () {
                $model = Auth::getProvider()->getModel();
                $user = $model::query()->orderBy('id')->first();
                abort_if($user === null, 404, '没有可登录的用户');

                Auth::login($user);

                return redirect(config('nova-site-core.quick_login.redirect', '/admin'));
            })->name('nova-site-core.quick-login');
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/nova-site-core.php' => config_path('nova-site-core.php'),
        ], 'nova-site-core-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_site_configs_table.php.stub'
                => $this->migrationPath('create_site_configs_table'),
            __DIR__.'/../database/migrations/create_ad_spots_table.php.stub'
                => $this->migrationPath('create_ad_spots_table', 1),
        ], 'nova-site-core-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/nova-site-core'),
        ], 'nova-site-core-views');
    }

    protected function migrationPath(string $name, int $offsetSeconds = 0): string
    {
        $timestamp = now()->addSeconds($offsetSeconds)->format('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$name}.php");
    }
}
