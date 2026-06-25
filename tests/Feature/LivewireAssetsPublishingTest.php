<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class LivewireAssetsPublishingTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/nova-admin-livewire-'.uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_it_skips_livewire_assets_when_source_manifest_is_missing(): void
    {
        $source = $this->tempDir.'/missing-source';
        $target = $this->tempDir.'/target';

        $this->provider($source, $target)->ensureLivewireAssetsPublishedForTest();

        $this->assertDirectoryDoesNotExist($target);
    }

    public function test_it_copies_livewire_assets_when_target_is_missing(): void
    {
        $source = $this->tempDir.'/source';
        $target = $this->tempDir.'/target';

        File::ensureDirectoryExists($source);
        File::put($source.'/manifest.json', '{"/livewire.js":"source-version"}');
        File::put($source.'/livewire.js', 'source asset');

        $this->provider($source, $target)->ensureLivewireAssetsPublishedForTest();

        $this->assertSame('{"/livewire.js":"source-version"}', File::get($target.'/manifest.json'));
        $this->assertSame('source asset', File::get($target.'/livewire.js'));
    }

    public function test_it_does_not_copy_livewire_assets_when_manifest_matches(): void
    {
        $source = $this->tempDir.'/source';
        $target = $this->tempDir.'/target';

        File::ensureDirectoryExists($source);
        File::ensureDirectoryExists($target);
        File::put($source.'/manifest.json', '{"/livewire.js":"same-version"}');
        File::put($source.'/livewire.js', 'source asset');
        File::put($target.'/manifest.json', '{"/livewire.js":"same-version"}');
        File::put($target.'/livewire.js', 'existing asset');

        $this->provider($source, $target)->ensureLivewireAssetsPublishedForTest();

        $this->assertSame('existing asset', File::get($target.'/livewire.js'));
    }

    public function test_it_updates_livewire_assets_when_manifest_differs(): void
    {
        $source = $this->tempDir.'/source';
        $target = $this->tempDir.'/target';

        File::ensureDirectoryExists($source);
        File::ensureDirectoryExists($target);
        File::put($source.'/manifest.json', '{"/livewire.js":"new-version"}');
        File::put($source.'/livewire.js', 'new asset');
        File::put($target.'/manifest.json', '{"/livewire.js":"old-version"}');
        File::put($target.'/livewire.js', 'old asset');

        $this->provider($source, $target)->ensureLivewireAssetsPublishedForTest();

        $this->assertSame('{"/livewire.js":"new-version"}', File::get($target.'/manifest.json'));
        $this->assertSame('new asset', File::get($target.'/livewire.js'));
    }

    private function provider(string $source, string $target): NovaAdminServiceProvider
    {
        return new class($this->app, $source, $target) extends NovaAdminServiceProvider
        {
            public function __construct($app, private string $source, private string $target)
            {
                parent::__construct($app);
            }

            public function ensureLivewireAssetsPublishedForTest(): void
            {
                $this->ensureLivewireAssetsPublished();
            }

            protected function livewireAssetsSourcePath(): string
            {
                return $this->source;
            }

            protected function livewireAssetsTargetPath(): string
            {
                return $this->target;
            }
        };
    }
}
