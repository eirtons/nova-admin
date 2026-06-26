<?php

namespace Inova\NovaAdmin\Tests\Unit;

use Inova\NovaAdmin\Services\AdCodeGeneratorService;
use PHPUnit\Framework\TestCase;

class AdCodeGeneratorServiceTest extends TestCase
{
    public function test_interstitial_only_generates_out_of_page_slot_without_anchor(): void
    {
        $code = (new AdCodeGeneratorService())->generate([
            'adType'               => 'interstitial',
            'interstitialAdUnitId' => '/23043164651/test_interstitial',
        ]);

        $this->assertStringContainsString("defineOutOfPageSlot(\n      '/23043164651/test_interstitial'", $code);
        $this->assertStringContainsString('OutOfPageFormat.INTERSTITIAL', $code);
        $this->assertStringContainsString('googletag.enableServices()', $code);
        // 仅插屏不含锚定 slot 与固定定位容器
        $this->assertStringNotContainsString('defineSlot(', $code);
        $this->assertStringNotContainsString('position: fixed', $code);
    }

    public function test_both_generates_anchor_slot_fixed_div_and_interstitial(): void
    {
        $code = (new AdCodeGeneratorService())->generate([
            'adType'               => 'both',
            'interstitialAdUnitId' => '/23043164651/test_interstitial',
            'anchorAdUnitId'       => '/23043164651/test_anchor',
            'anchorSizes'          => '[[990,90],[300,100],"fluid"]',
            'anchorDivId'          => 'div-gpt-ad-123',
            'anchorPosition'       => 'bottom',
        ]);

        // 锚定 slot：尺寸 JSON 原样直插
        $this->assertStringContainsString("defineSlot('/23043164651/test_anchor', [[990,90],[300,100],\"fluid\"], 'div-gpt-ad-123')", $code);
        // 固定定位容器 + 底部
        $this->assertStringContainsString("<div id='div-gpt-ad-123'", $code);
        $this->assertStringContainsString('position: fixed; bottom: 0;', $code);
        // 仍含插屏
        $this->assertStringContainsString('OutOfPageFormat.INTERSTITIAL', $code);
    }

    public function test_anchor_position_top_switches_style(): void
    {
        $code = (new AdCodeGeneratorService())->generate([
            'adType'               => 'both',
            'interstitialAdUnitId' => '/x/i',
            'anchorAdUnitId'       => '/x/a',
            'anchorSizes'          => '[[728,90]]',
            'anchorDivId'          => 'div-1',
            'anchorPosition'       => 'top',
        ]);

        $this->assertStringContainsString('position: fixed; top: 0;', $code);
    }

    public function test_html_special_chars_in_ids_are_escaped(): void
    {
        $code = (new AdCodeGeneratorService())->generate([
            'adType'               => 'interstitial',
            'interstitialAdUnitId' => "/x/i'\"<>",
        ]);

        $this->assertStringNotContainsString("/x/i'\"<>", $code);
        $this->assertStringContainsString('&#039;', $code);
    }
}
