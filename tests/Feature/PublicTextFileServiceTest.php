<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Orchestra\Testbench\TestCase;

class PublicTextFileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDir;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/nova-admin-'.uniqid();
        File::ensureDirectoryExists($this->tempDir);

        config(['nova-admin.ads_txt.path' => $this->tempDir.'/ads.txt']);
        config(['nova-admin.robots_txt.path' => $this->tempDir.'/robots.txt']);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_empty_ads_txt_save_deletes_the_public_file_by_default(): void
    {
        File::put(config('nova-admin.ads_txt.path'), 'google.com, pub-test, DIRECT');

        app(PublicTextFileService::class)->save('ads_txt', '');

        $this->assertFileDoesNotExist(config('nova-admin.ads_txt.path'));
    }

    public function test_robots_writes_static_file_with_resolved_sitemap_url(): void
    {
        $svc = app(PublicTextFileService::class);
        $svc->save('robots_txt', "User-agent: *\nSitemap: {url}/sitemap.xml");

        // 落静态文件，{url} 按 APP_URL 解析写入
        $path = config('nova-admin.robots_txt.path');
        $this->assertFileExists($path);

        $expected = rtrim((string) config('app.url'), '/').'/sitemap.xml';
        $this->assertStringContainsString($expected, File::get($path));
        // DB 仍存占位符，read() 输出也含解析后的绝对 URL
        $this->assertStringContainsString($expected, $svc->read('robots_txt'));
    }

    public function test_default_robots_blocks_admin_and_login_without_exposing_quick_login(): void
    {
        $content = app(PublicTextFileService::class)->defaultTemplate('robots_txt');

        $this->assertStringContainsString("Disallow: /admin\n", $content);
        $this->assertStringContainsString("Disallow: /login\n", $content);
        $this->assertStringNotContainsString('quick-login', $content);
    }
}
