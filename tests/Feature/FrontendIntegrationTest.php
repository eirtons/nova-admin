<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class FrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $viewDir;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('nova-admin.static_pages.footer_views', ['nova-test.footer']);
        $app['config']->set('nova-admin.ad_disabled_views', ['nova-test.no-ads']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewDir = sys_get_temp_dir().'/nova-admin-views-'.uniqid();
        File::ensureDirectoryExists($this->viewDir.'/nova-test');
        File::put($this->viewDir.'/nova-test/footer.blade.php', '{{ $footerPages->pluck(\'slug\')->implode(\',\') }}');
        File::put($this->viewDir.'/nova-test/no-ads.blade.php', '{{ var_export($section->ads_enabled, true) }}');
        View::addLocation($this->viewDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->viewDir);

        parent::tearDown();
    }

    public function test_footer_pages_are_active_pages_in_preset_order(): void
    {
        StaticPage::create(['slug' => 'custom', 'title' => 'Custom', 'content' => '', 'is_active' => true]);
        StaticPage::create(['slug' => 'privacy-policy', 'title' => 'Privacy', 'content' => '', 'is_active' => true]);
        StaticPage::create(['slug' => 'about', 'title' => 'About', 'content' => '', 'is_active' => true]);
        StaticPage::create(['slug' => 'faq', 'title' => 'FAQ', 'content' => '', 'is_active' => false]);

        $this->assertSame('about,privacy-policy,custom', trim(view('nova-test.footer')->render()));
    }

    public function test_disabled_views_get_ads_turned_off(): void
    {
        $this->assertSame('false', trim(view('nova-test.no-ads')->render()));
    }

    public function test_errors_bag_is_shared_for_sessionless_routes(): void
    {
        $this->assertInstanceOf(\Illuminate\Support\ViewErrorBag::class, View::shared('errors'));
    }

    public function test_nova_public_group_sends_cache_headers_without_cookies(): void
    {
        $this->app['env'] = 'production';
        Route::middleware('nova.public')->get('/nova-public-probe', fn () => 'ok');

        $response = $this->get('/nova-public-probe')->assertOk();

        $this->assertStringContainsString('s-maxage=', $response->headers->get('Cache-Control'));
        $this->assertCount(0, $response->headers->getCookies());
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        Route::get('/hsts-probe', fn () => 'ok');

        $this->get('http://localhost/hsts-probe')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/hsts-probe')->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        config(['nova-admin.security.hsts' => false]);
        $this->get('https://localhost/hsts-probe')->assertHeaderMissing('Strict-Transport-Security');
    }
}
