<?php

namespace Nova\NovaAdmin\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nova\NovaAdmin\Models\AdSpot;
use Nova\NovaAdmin\NovaAdminServiceProvider;
use Nova\NovaAdmin\Services\AdService;
use Orchestra\Testbench\TestCase;

class AdComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [NovaAdminServiceProvider::class];
    }

    public function test_body_ad_is_rendered_in_a_centered_container(): void
    {
        $this->app->instance(AdService::class, new class extends AdService
        {
            public function body(string $position): string
            {
                return '<ins data-position="'.$position.'"></ins>';
            }
        });

        $html = Blade::render('<x-ad-body position="home_banner1" />');

        $this->assertStringContainsString('width: 100% !important', $html);
        $this->assertStringContainsString('text-align: center !important', $html);
        $this->assertStringContainsString('<ins data-position="home_banner1"></ins>', $html);
    }

    public function test_empty_body_ad_does_not_render_a_container(): void
    {
        $this->app->instance(AdService::class, new class extends AdService
        {
            public function body(string $position): string
            {
                return '';
            }
        });

        $this->assertSame('', Blade::render('<x-ad-body position="home_banner1" />'));
    }

    public function test_unknown_body_ad_position_does_not_render_a_container_in_debug_mode(): void
    {
        config([
            'app.debug' => true,
            'nova-admin.ad_positions' => [],
        ]);

        $this->assertSame('', app(AdService::class)->body('unknown'));
        $this->assertSame('', Blade::render('<x-ad-body position="unknown" />'));
    }

    public function test_head_ad_is_rendered_without_a_container(): void
    {
        $this->app->instance(AdService::class, new class extends AdService
        {
            public function head(string $position): string
            {
                return '<script data-position="'.$position.'"></script>';
            }
        });

        $html = Blade::render('<x-ad-head position="global_head" />');

        $this->assertSame('<script data-position="global_head"></script>', $html);
    }

    public function test_empty_head_ad_does_not_render_anything(): void
    {
        $this->app->instance(AdService::class, new class extends AdService
        {
            public function head(string $position): string
            {
                return '';
            }
        });

        $this->assertSame('', Blade::render('<x-ad-head position="global_head" />'));
    }

    public function test_ad_changes_are_visible_without_clearing_cache(): void
    {
        $ad = AdSpot::query()->create([
            'position' => 'home_banner1',
            'body_code' => '<div>old</div>',
            'is_active' => true,
        ]);

        $this->assertSame('<div>old</div>', app(AdService::class)->body('home_banner1'));

        $ad->update(['body_code' => '<div>new</div>']);

        $this->assertSame('<div>new</div>', app(AdService::class)->body('home_banner1'));
    }
}
