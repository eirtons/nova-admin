<?php

namespace Inova\NovaAdmin\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inova\NovaAdmin\Models\AdSpot;
use Inova\NovaAdmin\NovaAdminServiceProvider;
use Orchestra\Testbench\TestCase;

class ImportSiteAdConfigCommandTest extends TestCase
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
        File::deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    private function import(array $payload): array
    {
        $file = $this->tempDir.'/payload.json';
        File::put($file, json_encode($payload));

        $exit = Artisan::call('ads:import-site-ad-config', ['file' => $file]);

        return [$exit, Artisan::output()];
    }

    private function decodeMarker(string $output): array
    {
        $this->assertMatchesRegularExpression(
            '/^__SITE_AD_CONFIG_RESULT_BEGIN__(.*)__SITE_AD_CONFIG_RESULT_END__$/s',
            trim($output),
        );

        preg_match('/__SITE_AD_CONFIG_RESULT_BEGIN__(.*)__SITE_AD_CONFIG_RESULT_END__/s', $output, $m);

        return json_decode($m[1], true);
    }

    public function test_it_writes_slots_and_ads_txt(): void
    {
        [$exit, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => [
                'global_head' => ['name' => 'Global', 'head_code' => '<script>loader</script>'],
                'home_banner_1' => ['name' => 'Home 1', 'head_code' => '<script>h1</script>', 'body_code' => '<div>h1</div>'],
                'anchor' => ['name' => 'Anchor', 'head_code' => '<script>anchor</script>'],
            ],
            'ads_txt' => "google.com, pub-123, DIRECT, f08c47fec0942fa0",
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame(0, $exit);
        $this->assertSame('success', $result['slots']['status']);
        $this->assertSame(['global_head', 'home_banner_1', 'anchor'], $result['slots']['written_positions']);
        $this->assertSame('success', $result['ads_txt']['status']);

        // 协议键 home_banner_1 落到本包 position home_banner1
        $spot = AdSpot::where('position', 'home_banner1')->first();
        $this->assertSame('<script>h1</script>', $spot->head_code);
        $this->assertSame('<div>h1</div>', $spot->body_code);
        $this->assertTrue($spot->is_active);

        $this->assertStringContainsString('pub-123', File::get($this->tempDir.'/ads.txt'));
    }

    public function test_it_overwrites_existing_spot(): void
    {
        AdSpot::create(['position' => 'global_head', 'head_code' => '<script>old</script>', 'is_active' => false]);

        $this->import([
            'meta' => ['protocol' => 1],
            'slots' => ['global_head' => ['name' => 'Global', 'head_code' => '<script>new</script>']],
        ]);

        $spot = AdSpot::where('position', 'global_head')->first();
        $this->assertSame('<script>new</script>', $spot->head_code);
        $this->assertTrue($spot->is_active);
        $this->assertSame(1, AdSpot::where('position', 'global_head')->count());
    }

    public function test_parts_not_delivered_are_absent_from_the_result(): void
    {
        [, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => ['global_head' => ['name' => 'Global', 'head_code' => '<script>x</script>']],
        ]);

        $result = $this->decodeMarker($output);

        $this->assertArrayHasKey('slots', $result);
        $this->assertArrayNotHasKey('ads_txt', $result);
    }

    public function test_unsupported_protocol_fails_both_parts(): void
    {
        [$exit, $output] = $this->import(['meta' => ['protocol' => 99], 'slots' => []]);

        $result = $this->decodeMarker($output);

        $this->assertSame(1, $exit);
        $this->assertSame('failed', $result['slots']['status']);
        $this->assertSame('failed', $result['ads_txt']['status']);
        $this->assertStringContainsString('99', $result['slots']['reason']);
    }

    public function test_unknown_protocol_key_rolls_back_every_slot(): void
    {
        [$exit, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => [
                'global_head' => ['name' => 'Global', 'head_code' => '<script>x</script>'],
                'sidebar_banner' => ['name' => 'Sidebar', 'head_code' => '<script>y</script>'],
            ],
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame(1, $exit);
        $this->assertSame('failed', $result['slots']['status']);
        $this->assertStringContainsString('sidebar_banner', $result['slots']['reason']);
        $this->assertSame(0, AdSpot::count());
    }

    public function test_missing_global_head_fails(): void
    {
        [, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => ['home_banner_1' => ['name' => 'Home 1', 'head_code' => '<script>x</script>']],
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame('failed', $result['slots']['status']);
        $this->assertSame(0, AdSpot::count());
    }

    public function test_malformed_slot_rolls_back_the_whole_batch(): void
    {
        [, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => [
                'global_head' => ['name' => 'Global', 'head_code' => '<script>x</script>'],
                'home_banner_1' => ['name' => 'Home 1'], // 缺 head_code
            ],
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame('failed', $result['slots']['status']);
        $this->assertSame(0, AdSpot::count());
    }

    public function test_position_not_enabled_on_this_site_fails(): void
    {
        config(['nova-admin.ad_positions' => ['global_head' => '全局 Head']]);

        [, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'slots' => [
                'global_head' => ['name' => 'Global', 'head_code' => '<script>x</script>'],
                'interstitial' => ['name' => 'Interstitial', 'head_code' => '<script>y</script>'],
            ],
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame('failed', $result['slots']['status']);
        $this->assertStringContainsString('interstitial', $result['slots']['reason']);
        $this->assertSame(0, AdSpot::count());
    }

    public function test_unreadable_file_fails_both_parts(): void
    {
        $exit = Artisan::call('ads:import-site-ad-config', ['file' => $this->tempDir.'/nope.json']);
        $result = $this->decodeMarker(Artisan::output());

        $this->assertSame(1, $exit);
        $this->assertSame('failed', $result['slots']['status']);
        $this->assertSame('failed', $result['ads_txt']['status']);
    }

    public function test_ads_txt_write_failure_is_reported_as_failed(): void
    {
        config(['nova-admin.ads_txt.path' => $this->tempDir.'/missing-dir/ads.txt']);

        [$exit, $output] = $this->import([
            'meta' => ['protocol' => 1],
            'ads_txt' => 'google.com, pub-123, DIRECT',
        ]);

        $result = $this->decodeMarker($output);

        $this->assertSame(1, $exit);
        $this->assertSame('failed', $result['ads_txt']['status']);
    }
}
