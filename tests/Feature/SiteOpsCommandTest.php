<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Inova\NovaAdmin\SitePresets\SitePresetProvider;
use Inova\NovaAdmin\SitePresets\SwitchableSitePreset;
use Orchestra\Testbench\TestCase;

class SiteOpsCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        FakeTheme::$current = 'aurora';
        config()->set('nova-admin.site_presets', [
            'theme' => ['label' => '主题', 'provider' => FakeTheme::class],
            'vertical' => ['label' => '领域', 'provider' => FakeFixedVertical::class],
        ]);
    }

    private function exec(array $payload): array
    {
        $file = tempnam(sys_get_temp_dir(), 'site-ops');
        File::put($file, json_encode(['meta' => ['protocol' => 1]] + $payload));
        Artisan::call('nova-admin:site-ops', ['file' => $file]);
        File::delete($file);

        preg_match('/__SITE_OPS_RESULT_BEGIN__(.*?)__SITE_OPS_RESULT_END__/s', Artisan::output(), $m);

        return json_decode($m[1] ?? 'null', true) ?? [];
    }

    public function test_read_only_returns_current_presets_of_switchable_providers(): void
    {
        // 未实现 SwitchableSitePreset 的预设不出现在 presets_current
        $this->assertSame(['presets_current' => ['theme' => 'aurora']], $this->exec([]));
    }

    public function test_applies_a_preset_and_reports_the_new_current_value(): void
    {
        $result = $this->exec(['presets' => ['theme' => 'bento']]);

        $this->assertSame(['status' => 'success'], $result['presets']['theme']);
        $this->assertSame('bento', $result['presets_current']['theme']);
    }

    public function test_rejects_out_of_list_values_and_non_switchable_presets(): void
    {
        $result = $this->exec(['presets' => ['theme' => 'retired', 'vertical' => 'pets']]);

        $this->assertSame('failed', $result['presets']['theme']['status']);
        $this->assertSame('not_applicable', $result['presets']['vertical']['status']);
        $this->assertSame('aurora', FakeTheme::$current);
    }

    public function test_writes_robots_txt_independently(): void
    {
        $result = $this->exec(['robots_txt' => "User-agent: *\nDisallow: /admin"]);

        $this->assertSame(['status' => 'success'], $result['robots_txt']);
        $this->assertArrayNotHasKey('ads_txt', $result);
        $this->assertStringContainsString('Disallow: /admin', File::get(public_path('robots.txt')));
        File::delete(public_path('robots.txt'));
    }

    public function test_wrong_protocol_still_emits_a_marker(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'site-ops');
        File::put($file, json_encode(['meta' => ['protocol' => 99]]));

        $this->assertSame(1, Artisan::call('nova-admin:site-ops', ['file' => $file]));
        $this->assertStringContainsString('__SITE_OPS_RESULT_BEGIN__{"error"', Artisan::output());
        File::delete($file);
    }
}

class FakeTheme implements SitePresetProvider, SwitchableSitePreset
{
    public static ?string $current = null;

    public function options(): array
    {
        return ['aurora' => 'Aurora', 'bento' => 'Bento'];
    }

    public function newOptions(): array
    {
        return [];
    }

    public function current(): ?string
    {
        return self::$current;
    }

    public function apply(string $slug): void
    {
        self::$current = $slug;
    }
}

class FakeFixedVertical implements SitePresetProvider
{
    public function options(): array
    {
        return ['pets' => 'Pets'];
    }

    public function newOptions(): array
    {
        return [];
    }
}
