<?php

namespace Inova\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Inova\NovaAdmin\Services\AdService;
use Inova\NovaAdmin\Services\EdgeCacheService;

class AdSpot extends Model
{
    protected $table = 'ad_spots';

    protected $fillable = [
        'position',
        'head_code',
        'body_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // AdService 在请求内缓存全部启用广告位；后台一保存就得让缓存失效。
        static::saved(fn () => static::flushCaches());
        static::deleted(fn () => static::flushCaches());
    }

    /**
     * 清空并按配置填充全部广告位的测试代码并启用。返回填充条数。
     */
    public static function seedTestSpots(): int
    {
        static::query()->delete();

        $count = 0;

        foreach (config('nova-admin.ad_positions', []) as $position => $label) {
            // 结构对齐 webdeploy 下发的 GAM 代码：Banner 有 head + 定尺寸 body，out-of-page 位只有 head
            [$headCode, $bodyCode] = match ($position) {
                'global_head'  => [static::testHeadScript(), null],
                'anchor'       => [static::testAnchorScript($label), null],
                'interstitial' => [static::testInterstitialScript($label), null],
                default        => static::testBannerCode($label),
            };

            static::query()->create([
                'position'  => $position,
                'head_code' => $headCode,
                'body_code' => $bodyCode,
                'is_active' => true,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 禁用所有广告。返回受影响条数。
     */
    public static function deactivateAll(): int
    {
        $affected = static::query()->update(['is_active' => false]);

        // 批量更新不触发模型事件，得手动让缓存失效
        static::flushCaches();

        return $affected;
    }

    /** 广告代码直出在 HTML 里，边缘副本不清就一直是旧广告。 */
    protected static function flushCaches(): void
    {
        app(AdService::class)->flush();
        app(EdgeCacheService::class)->purgeLater('ad_spots');
    }

    private static function testHeadScript(): string
    {
        return <<<HTML
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0000000000000000" crossorigin="anonymous"></script>
        <script>console.log('[测试广告] 全局 Head script 已加载');</script>
        HTML;
    }

    /** Banner：webdeploy 默认尺寸 Fluid; 300x250，body 与真实代码一样按最大固定尺寸占位。 */
    private static function testBannerCode(string $label): array
    {
        return [
            "<script>console.log('[测试广告] {$label} head 已加载');</script>",
            <<<HTML
            <div style="width:300px;height:250px;margin:0 auto;display:flex;align-items:center;justify-content:center;flex-direction:column;box-sizing:border-box;background:#ffcc00;border:2px dashed #333;color:#000;font:bold 14px/1.6 sans-serif;text-align:center;">
                [测试广告] {$label}<br>300×250
            </div>
            HTML,
        ];
    }

    /** Anchor：webdeploy 默认尺寸 320x50 / 320x100 / 300x50 / 300x100，取 320x50 贴底展示。 */
    private static function testAnchorScript(string $label): string
    {
        return static::testOverlayScript(
            'position:fixed;left:0;right:0;bottom:0;z-index:2147483646;display:flex;justify-content:center;background:rgba(0,0,0,.15);',
            'width:320px;height:50px;',
            "[测试广告] {$label} 320×50",
        );
    }

    /** Interstitial：webdeploy 默认尺寸 320x480 / 480x320，取 320x480 全屏遮罩居中，点 × 关闭。 */
    private static function testInterstitialScript(string $label): string
    {
        return static::testOverlayScript(
            'position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.6);',
            'width:320px;height:480px;max-height:90vh;',
            "[测试广告] {$label} 320×480",
        );
    }

    /** out-of-page 位没有 body，浮层由 head 脚本在 DOM 就绪后自行插入（与 GPT 一致）。 */
    private static function testOverlayScript(string $wrapStyle, string $boxStyle, string $text): string
    {
        $box = $boxStyle.'position:relative;display:flex;align-items:center;justify-content:center;box-sizing:border-box;background:#ffcc00;border:2px dashed #333;color:#000;font:bold 14px/1.4 sans-serif;text-align:center;';

        return <<<HTML
        <script>
        document.addEventListener('DOMContentLoaded', function () {
          var wrap = document.createElement('div');
          wrap.style.cssText = '{$wrapStyle}';
          wrap.innerHTML = '<div style="{$box}">{$text}<span style="position:absolute;top:0;right:6px;cursor:pointer;font-size:18px;">×</span></div>';
          wrap.querySelector('span').onclick = function () { wrap.remove(); };
          document.body.appendChild(wrap);
        });
        </script>
        HTML;
    }
}
