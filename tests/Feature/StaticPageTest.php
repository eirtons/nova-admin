<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class StaticPageTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
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
}
