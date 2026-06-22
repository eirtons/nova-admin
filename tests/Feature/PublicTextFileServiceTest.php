<?php

namespace Nbutl\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Nbutl\NovaAdmin\NovaAdminServiceProvider;
use Nbutl\NovaAdmin\Services\PublicTextFileService;
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
}
