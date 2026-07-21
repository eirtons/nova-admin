<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class TrustProxiesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    /**
     * 反代转发的 X-Forwarded-Proto: https 必须被认下来，否则生成的
     * Livewire update 端点是 http://，被浏览器按 Mixed Content 拦掉。
     */
    public function test_it_generates_https_urls_behind_a_proxy(): void
    {
        Route::get('/proxied', fn () => url()->current());

        $this->get('/proxied', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertSee('https://', false);
    }
}
