<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\SiteConfigService;
use Orchestra\Testbench\TestCase;

class SeoComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('nova-admin.site_defaults', [
            'site_name' => 'Acme',
            'subtitle' => 'Tools for makers',
            'meta_title_template' => '{title} | {site_name}',
            'meta_description' => 'Default description',
            'meta_keywords' => '',
            'favicon_path' => null,
        ]);
    }

    public function test_page_title_uses_the_template_and_escapes_once(): void
    {
        $html = Blade::render("@section('title', 'Tom & Jerry')\n<x-nova-seo />");

        $this->assertStringContainsString('<title>Tom &amp; Jerry | Acme</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Default description">', $html);
        $this->assertStringNotContainsString('name="keywords"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost">', $html);
        $this->assertStringNotContainsString('rel="icon"', $html);
    }

    public function test_page_without_title_gets_site_name_and_subtitle(): void
    {
        $this->assertStringContainsString('<title>Acme - Tools for makers</title>', Blade::render('<x-nova-seo />'));
    }

    public function test_saved_site_settings_override_defaults(): void
    {
        $settings = app(SiteConfigService::class);
        $settings->set('site_name', 'Saved');
        $settings->set('meta_title_template', '%s · {site_name}');
        $settings->set('meta_keywords', 'a, b');
        $settings->set('favicon_path', 'site/icon.png');

        $html = Blade::render('<x-nova-seo title="Guide" description="Page desc" image="https://x.test/og.png" />');

        $this->assertStringContainsString('<title>Guide · Saved</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Page desc">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="a, b">', $html);
        $this->assertStringContainsString('<link rel="icon" href="http://localhost/storage/site/icon.png">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function test_page_sections_override_keywords_robots_and_og_type(): void
    {
        app(SiteConfigService::class)->set('meta_keywords', 'site, wide');

        $html = Blade::render(
            "@section('title', 'Snake')\n@section('keywords', 'snake, arcade')\n"
            ."@section('robots', 'noindex, follow')\n@section('og_type', 'article')\n"
            ."@section('og_image', 'https://x.test/snake.png')\n<x-nova-seo />"
        );

        $this->assertStringContainsString('<meta name="keywords" content="snake, arcade">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Snake | Acme">', $html);
        $this->assertStringContainsString('<meta name="twitter:description" content="Default description">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="https://x.test/snake.png">', $html);
    }

    public function test_attributes_override_keywords_robots_and_og_type(): void
    {
        $html = Blade::render('<x-nova-seo keywords="k1" robots="noindex" og-type="article" />');

        $this->assertStringContainsString('<meta name="keywords" content="k1">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_optional_tags_are_omitted_without_values(): void
    {
        $html = Blade::render('<x-nova-seo />');

        $this->assertStringNotContainsString('name="robots"', $html);
        $this->assertStringNotContainsString('twitter:image', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
    }

    public function test_site_setting_helper_falls_back_to_defaults(): void
    {
        $this->assertSame('Acme', site_setting('site_name'));
        $this->assertNull(site_media_url('favicon_path'));
    }
}
