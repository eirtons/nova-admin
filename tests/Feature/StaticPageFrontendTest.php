<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\SitemapService;
use Orchestra\Testbench\TestCase;

class StaticPageFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // web 中间件组含 cookie 加密，测试环境需要 app key
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('nova-admin.static_pages.frontend.enabled', true);
        $app['config']->set('nova-admin.sitemap.cache_ttl', 0);
    }

    public function test_active_static_page_is_rendered_on_frontend(): void
    {
        StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<h1>About Us</h1><p>Body text here.</p>',
            'is_active' => true,
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<h1>About Us</h1>', false)
            ->assertSee('Body text here.')
            ->assertDontSee('<h1>About Us</h1><h1>', false);
    }

    public function test_inactive_page_returns_404(): void
    {
        StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<p>Hidden.</p>',
            'is_active' => false,
        ]);

        $this->get('/about')->assertNotFound();
    }

    public function test_non_preset_slug_is_not_hijacked(): void
    {
        $this->get('/some-article-slug')->assertNotFound();
    }

    public function test_route_is_named_pages_show(): void
    {
        $this->assertSame(url('/about'), route('pages.show', 'about'));
    }

    public function test_active_pages_enter_sitemap(): void
    {
        StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<p>Body.</p>',
            'is_active' => true,
        ]);

        $xml = $this->app->make(SitemapService::class)->xml();

        $this->assertStringContainsString(url('/about'), $xml);
    }
}
