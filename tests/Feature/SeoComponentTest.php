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

    public function test_site_setting_helper_falls_back_to_defaults(): void
    {
        $this->assertSame('Acme', site_setting('site_name'));
        $this->assertNull(site_media_url('favicon_path'));
    }
}
