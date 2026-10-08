<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Filament\Support\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inova\NovaAdmin\Models\AdSpot;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\EdgeCacheService;
use Inova\NovaAdmin\Services\SiteConfigService;
use Livewire\LivewireServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase;

class EdgeCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // StaticPage 的富文本渲染依赖 Filament Support
        return [LivewireServiceProvider::class, SupportServiceProvider::class, NovaAdminServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        config([
            'nova-admin.cloudflare.api_token' => 'tok',
            'nova-admin.cloudflare.zone_id' => 'zone123',
            'app.url' => 'https://a.example.com',
        ]);
    }

    private function edgeCache(): EdgeCacheService
    {
        return $this->app->make(EdgeCacheService::class);
    }

    private function fakeCloudflare(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => [['id' => 'zoneABC']]])]);
    }

    public function test_purges_only_this_site_host(): void
    {
        $this->fakeCloudflare();

        $this->assertTrue($this->edgeCache()->purge('test'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
            && $request->hasHeader('Authorization', 'Bearer tok')
            // 同 zone 下挂着别的站，不能 purge_everything
            && $request['hosts'] === ['a.example.com']
            && ! isset($request['purge_everything']));
    }

    public function test_resolves_zone_from_parent_domain_and_caches_it(): void
    {
        config(['nova-admin.cloudflare.zone_id' => '']);
        Http::fake([
            'api.cloudflare.com/client/v4/zones?name=a.example.com' => Http::response(['success' => true, 'result' => []]),
            'api.cloudflare.com/client/v4/zones?name=example.com' => Http::response(['success' => true, 'result' => [['id' => 'zoneABC']]]),
            'api.cloudflare.com/client/v4/zones/zoneABC/purge_cache' => Http::response(['success' => true]),
        ]);

        $this->assertTrue($this->edgeCache()->purge('test'));
        $this->assertTrue($this->edgeCache()->purge('test'));

        // 两次查 zone + 两次清；第二次清不再查 zone
        Http::assertSentCount(4);
    }

    public function test_skips_without_token_and_in_local_or_testing(): void
    {
        Http::fake();

        config(['nova-admin.cloudflare.api_token' => '']);
        $this->assertFalse($this->edgeCache()->purge('test'));

        config(['nova-admin.cloudflare.api_token' => 'tok']);
        foreach (['local', 'testing'] as $env) {
            $this->app['env'] = $env;
            $this->assertFalse($this->edgeCache()->purge('test'));
        }

        Http::assertNothingSent();
    }

    public function test_api_failure_does_not_throw(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['code' => 10000]]], 403)]);

        $this->assertFalse($this->edgeCache()->purge('test'));
    }

    public function test_admin_changes_purge_once_when_request_terminates(): void
    {
        // 站点设置逐字段写、webdeploy 下发多个广告位：一次请求里多次写入只清一次
        $this->fakeCloudflare();

        $config = $this->app->make(SiteConfigService::class);
        $config->set('site_name', 'A');
        $config->set('meta_description', 'B');
        $config->forget('meta_description');
        AdSpot::create(['position' => 'home_banner1', 'head_code' => '<script></script>', 'is_active' => true]);
        AdSpot::deactivateAll();
        StaticPage::create(['slug' => 'about', 'title' => 'About', 'content' => '<p>x</p>', 'is_active' => true]);
        Http::assertNothingSent();

        $this->app->terminate();

        Http::assertSentCount(1);
    }

    public function test_pending_purge_is_flushed_after_each_queue_job(): void
    {
        // 队列 worker 常驻不走 terminating，任务里的改动要在任务结束时清
        $this->fakeCloudflare();

        purge_edge_cache('job');
        $this->app['events']->dispatch(new JobProcessed('sync', Mockery::mock(Job::class)));

        Http::assertSentCount(1);
    }

    public function test_helper_can_purge_immediately(): void
    {
        $this->fakeCloudflare();

        $this->assertTrue(purge_edge_cache('switch', now: true));
        Http::assertSentCount(1);
    }

    public function test_artisan_command_purges_and_reports_failure(): void
    {
        $this->fakeCloudflare();
        $this->assertSame(0, Artisan::call('nova-admin:purge-edge-cache'));

        config(['nova-admin.cloudflare.api_token' => '']);
        $this->assertSame(1, Artisan::call('nova-admin:purge-edge-cache'));
    }
}
