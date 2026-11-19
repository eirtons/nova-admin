<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inova\NovaAdmin\Models\StaticPage;
use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class StaticPageTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, SupportServiceProvider::class, NovaAdminServiceProvider::class];
    }

    public function test_leading_h1_becomes_title_and_is_stripped_from_body_html(): void
    {
        $page = StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<h1>About Our Site</h1><p>We publish data.</p>',
            'is_active' => true,
        ]);

        $this->assertSame('About Our Site', $page->title);
        $this->assertSame('<p>We publish data.</p>', $page->body_html);
        // content 原样保留 H1，编辑器所见即所得
        $this->assertStringContainsString('<h1>About Our Site</h1>', $page->content);
    }

    public function test_title_and_derived_meta_description_are_plain_text(): void
    {
        $page = StaticPage::create([
            'slug' => 'faq',
            'title' => 'FAQ',
            'content' => '<h1>Staff &amp; Tempo FAQ</h1><p>Tom &amp; Jerry&#039;s tools.</p>',
            'is_active' => true,
        ]);

        // 存纯文本，Blade 输出时只转义一次；存实体会在页面上显示成 &amp;
        $this->assertSame('Staff & Tempo FAQ', $page->title);
        $this->assertSame("Tom & Jerry's tools.", $page->meta_description);
    }

    public function test_content_without_h1_keeps_given_title(): void
    {
        $page = StaticPage::create([
            'slug' => 'faq',
            'title' => 'FAQ',
            'content' => '<p>Questions.</p>',
            'is_active' => true,
        ]);

        $this->assertSame('FAQ', $page->title);
        $this->assertSame('<p>Questions.</p>', $page->body_html);
    }

    public function test_blank_meta_description_is_derived_from_body(): void
    {
        $page = StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<h1>About</h1><p>We publish EV data for everyone.</p>',
            'is_active' => true,
        ]);

        $this->assertSame('We publish EV data for everyone.', $page->meta_description);
    }

    public function test_explicit_meta_description_is_kept(): void
    {
        $page = StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'meta_description' => 'Hand-written description.',
            'content' => '<p>Body.</p>',
            'is_active' => true,
        ]);

        $this->assertSame('Hand-written description.', $page->meta_description);
    }

    public function test_placeholders_follow_current_site_settings(): void
    {
        $config = app(\Inova\NovaAdmin\Services\SiteConfigService::class);
        $config->set('site_name', 'Old & Co');
        $config->set('contact_email', 'old@example.com');

        $page = StaticPage::create([
            'slug' => 'about',
            'title' => 'About',
            'content' => '<h1>About {{site_name}}</h1><p>{{site_name}} <a href="mailto:{{contact_email}}">{{contact_email}}</a></p>',
            'meta_description' => 'x',
            'is_active' => true,
        ]);

        // 占位符原样入库，后台编辑器看到的仍是占位符
        $this->assertStringContainsString('{{contact_email}}', $page->fresh()->content);

        $config->set('site_name', 'New & Co');
        $config->set('contact_email', 'new@example.com');
        $page = $page->fresh();

        $this->assertSame('About New & Co', $page->title);
        $this->assertSame('<p>New &amp; Co <a href="mailto:new@example.com">new@example.com</a></p>', $page->body_html);
    }

    public function test_migration_restores_placeholders_from_current_settings(): void
    {
        $config = app(\Inova\NovaAdmin\Services\SiteConfigService::class);
        $config->set('site_name', 'FilmHub');
        $config->set('contact_email', 'hi@filmhub.com');

        // 2.9 前 seed 写死的正文
        $page = StaticPage::create([
            'slug' => 'contact',
            'title' => 'Contact',
            'content' => '<h1>Contact</h1><p>FilmHub team: <a href="mailto:hi@filmhub.com">hi@filmhub.com</a>, see https://filmhub.com/about or FilmHubs.</p>',
            'meta_description' => 'x',
            'is_active' => true,
        ]);
        $before = $page->body_html;

        (require __DIR__.'/../../database/migrations/2026_11_19_000001_restore_static_page_placeholders.php')->up();

        $page = $page->fresh();
        $this->assertSame(
            '<h1>Contact</h1><p>{{site_name}} team: <a href="mailto:{{contact_email}}">{{contact_email}}</a>, see https://filmhub.com/about or FilmHubs.</p>',
            $page->content,
        );
        // 迁移当下页面输出不变（渲染器把正文里的 @ 输出为 &#64;，解码后比较）
        $this->assertSame(html_entity_decode($before), html_entity_decode($page->body_html));
    }

    public function test_default_privacy_policy_meets_adsense_requirements(): void
    {
        // AdSense 政策要求：说明 Google 作为第三方供应商使用 Cookie 投放广告，并给出了解 / 退出的链接
        $html = file_get_contents(__DIR__.'/../../resources/defaults/static-pages/privacy-policy.html');

        $this->assertStringContainsString('Third-party vendors, including Google, also use cookies', $html);
        $this->assertStringContainsString('https://www.google.com/settings/ads', $html);
        $this->assertStringContainsString('https://policies.google.com/technologies/ads', $html);
    }
}
