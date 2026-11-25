<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Filament\Support\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inova\NovaAdmin\Facades\Sitemap;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\SitemapService;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;

class SitemapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, SupportServiceProvider::class, NovaAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('nova-admin.sitemap.enabled', true);
        $app['config']->set('nova-admin.sitemap.cache_ttl', 1800);
        $app['config']->set('nova-admin.static_pages.frontend.enabled', true);
    }

    /** @return list<string> */
    private function extractLocs(string $xml): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);

        return $m[1];
    }

    public function test_route_serves_xml_with_noindex_header(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex');

        // 首页相对路径 '/' 与 Laravel url('/') 一致，不额外追加尾斜杠
        $this->assertStringContainsString('<loc>https://example.com</loc>', $response->getContent());
    }

    public function test_provider_priority_ordering_places_static_pages_last(): void
    {
        StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<p>About body.</p>',
            'is_active' => true,
        ]);
        StaticPage::create([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'content' => '<p>Privacy body.</p>',
            'is_active' => true,
        ]);

        // 默认 priority=0 的两个业务 provider，以及更高优先级 priority=10 的栏目 provider
        Sitemap::register(fn () => ['/articles/a', '/articles/b']);
        Sitemap::register(fn () => ['/guides']);
        Sitemap::register(fn () => ['/news'], priority: 10);

        $locs = $this->extractLocs(Sitemap::build());

        $this->assertSame([
            'https://example.com',
            'https://example.com/news',
            'https://example.com/articles/a',
            'https://example.com/articles/b',
            'https://example.com/guides',
            'https://example.com/about',
            'https://example.com/privacy-policy',
        ], $locs);
    }

    public function test_deduplicates_urls_and_skips_blank_locs(): void
    {
        Sitemap::register(fn () => [
            '',
            '   ',
            ['loc' => ''],
            ['lastmod' => '2026-01-01'],
            ['loc' => '/'],
            ['loc' => '/dup', 'lastmod' => '2026-01-01'],
            ['loc' => 'https://example.com/dup', 'lastmod' => '2026-02-02'],
        ]);

        $xml = Sitemap::build();

        $this->assertSame([
            'https://example.com',
            'https://example.com/dup',
        ], $this->extractLocs($xml));
        $this->assertStringContainsString('<lastmod>2026-01-01</lastmod>', $xml);
        $this->assertStringNotContainsString('2026-02-02', $xml);
    }

    public function test_relative_urls_use_app_url_even_when_requested_from_other_host(): void
    {
        StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<p>Body.</p>',
            'is_active' => true,
        ]);

        // 冷缓存时即便被 IP 或异常 Host 命中，相对路径和静态页仍应产出 APP_URL 域名
        $xml = $this->get('http://127.0.0.1/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>https://example.com</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/about</loc>', $xml);
        $this->assertStringNotContainsString('127.0.0.1', $xml);
    }

    public function test_rebuild_atomically_replaces_cache_and_returns_count(): void
    {
        $first = Sitemap::xml();
        $this->assertCount(1, $this->extractLocs($first));

        Sitemap::register(fn () => ['/posts/1', '/posts/2']);

        $count = Sitemap::rebuild();

        $this->assertSame(3, $count);
        $this->assertSame([
            'https://example.com',
            'https://example.com/posts/1',
            'https://example.com/posts/2',
        ], $this->extractLocs((string) Sitemap::cachedXml()));
    }

    public function test_url_limit_throws_preserving_cache_on_rebuild_and_truncates_on_xml(): void
    {
        $service = new class extends SitemapService
        {
            protected function maxUrls(): int
            {
                return 2;
            }
        };

        // 先缓存一份合法的 2 条 sitemap
        $service->register(fn () => ['/first']);
        $this->assertSame(2, $service->rebuild());
        $validBefore = $service->cachedXml();

        // 追加超过上限的流式生成器，验证超限时立刻停止迭代
        $yielded = 0;
        $service->register(function () use (&$yielded): \Generator {
            for ($i = 1; $i <= 10; $i++) {
                $yielded++;
                yield "/extra-{$i}";
            }
        });

        try {
            $service->rebuild();
            $this->fail('rebuild() 超过单文件上限应抛 RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('超过单文件协议上限 2', $e->getMessage());
        }

        // rebuild 失败不能破坏原有缓存
        $this->assertSame($validBefore, $service->cachedXml());
        // 流式迭代在碰到第 1 条超限项时立即终止，不会读完后续 9 条
        $this->assertSame(1, $yielded);

        // HTTP xml() 走非严格模式：记告警并截断，不返回 500
        $service->forget();
        Log::shouldReceive('warning')->once();
        $yielded = 0;

        $xml = $service->xml();

        $this->assertSame([
            'https://example.com',
            'https://example.com/first',
        ], $this->extractLocs($xml));
        $this->assertStringEndsWith("</urlset>\n", $xml);
        $this->assertSame(1, $yielded);
    }

    public function test_byte_limit_is_enforced(): void
    {
        $service = new class extends SitemapService
        {
            protected function maxBytes(): int
            {
                return 220;
            }
        };

        $service->register(fn () => ['/a-very-long-url-segment-that-exceeds-the-byte-limit']);

        try {
            $service->rebuild();
            $this->fail('rebuild() 超过体积上限应抛 RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('体积超过单文件协议上限', $e->getMessage());
        }

        Log::shouldReceive('warning')->once();
        $xml = $service->xml();

        $this->assertSame(['https://example.com'], $this->extractLocs($xml));
        $this->assertLessThanOrEqual(220, strlen($xml));
    }

    public function test_static_page_save_and_delete_invalidate_sitemap_cache(): void
    {
        $this->assertStringNotContainsString('/about', Sitemap::xml());
        $this->assertNotNull(Sitemap::cachedXml());

        $page = StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<p>Body.</p>',
            'is_active' => true,
        ]);

        $this->assertNull(Sitemap::cachedXml());
        $this->assertStringContainsString('https://example.com/about', Sitemap::xml());

        $page->delete();

        $this->assertNull(Sitemap::cachedXml());
        $this->assertStringNotContainsString('/about', Sitemap::xml());
    }

    public function test_build_sitemap_command_purges_only_sitemap_url_when_changed(): void
    {
        $this->app['env'] = 'production';
        config([
            'nova-admin.cloudflare.api_token' => 'tok',
            'nova-admin.cloudflare.zone_id' => 'zone123',
        ]);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        // 首次重建（原缓存为空）→ 仅清 /sitemap.xml 单 URL
        $this->assertSame(0, Artisan::call('nova-admin:build-sitemap'));
        $this->assertStringContainsString('1 个 URL', Artisan::output());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
            && $request['files'] === ['https://example.com/sitemap.xml']
            && ! isset($request['hosts']));

        // 内容未变再次重建 → 不重复调 Cloudflare
        $this->assertSame(0, Artisan::call('nova-admin:build-sitemap'));
        Http::assertSentCount(1);

        // 内容变化后重建 → 再次清 /sitemap.xml
        Sitemap::register(fn () => ['/fresh']);
        $this->assertSame(0, Artisan::call('nova-admin:build-sitemap'));
        $this->assertStringContainsString('2 个 URL', Artisan::output());
        Http::assertSentCount(2);
    }

    public function test_clear_sitemap_cache_command_forgets_cache_and_purges_sitemap_url(): void
    {
        $this->app['env'] = 'production';
        config([
            'nova-admin.cloudflare.api_token' => 'tok',
            'nova-admin.cloudflare.zone_id' => 'zone123',
        ]);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        Sitemap::xml();
        $this->assertNotNull(Sitemap::cachedXml());

        $this->assertSame(0, Artisan::call('nova-admin:clear-sitemap-cache'));

        $this->assertNull(Sitemap::cachedXml());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['files'] === ['https://example.com/sitemap.xml']);
    }

    public function test_null_cache_store_works_without_lock_provider(): void
    {
        config(['nova-admin.sitemap.cache_store' => 'null']);

        $this->assertStringContainsString('<loc>https://example.com</loc>', Sitemap::xml());
        $this->assertSame(1, Sitemap::rebuild());
    }
}
