<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\Services\PublicTextFileService;
use Inova\NovaAdmin\Services\SiteConfigService;
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
    public function test_large_ads_txt_is_stored_in_both_database_and_static_file(): void
    {
        $lines = [];
        for ($i = 0; $i < 5000; $i++) {
            $lines[] = "adform.com, {$i}, RESELLER, 9f5210a2f0999e32";
        }
        $content = implode("\n", $lines);

        $result = app(PublicTextFileService::class)->save('ads_txt', $content);

        $this->assertTrue($result['file_written']);

        // 数据库：整份内容原样落 site_configs（longText，不截断）
        $this->assertSame($content, app(SiteConfigService::class)->get('ads_txt_content'));

        // 静态文件：内容完整，且原子写不留临时文件
        $path = config('nova-admin.ads_txt.path');
        $this->assertSame($content.PHP_EOL, File::get($path));
        $this->assertSame([], glob($this->tempDir.'/*.tmp'));
    }

    public function test_failed_write_keeps_the_previous_ads_txt_intact(): void
    {
        $path = config('nova-admin.ads_txt.path');
        File::put($path, "old.com, 1, DIRECT\n");

        // 目录不可写 → 临时文件写不出去，旧 ads.txt 必须原封不动
        chmod($this->tempDir, 0500);

        try {
            $result = app(PublicTextFileService::class)->save('ads_txt', "new.com, 2, DIRECT");
        } finally {
            chmod($this->tempDir, 0700);
        }

        $this->assertFalse($result['file_written']);
        $this->assertSame("old.com, 1, DIRECT\n", File::get($path));
        // 写文件失败也要落库，/ads.txt 路由兜底才有内容
        $this->assertSame("new.com, 2, DIRECT", app(SiteConfigService::class)->get('ads_txt_content'));
    }
}
