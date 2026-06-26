<?php

namespace Inova\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;

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

    /**
     * 清空并按配置填充全部广告位的测试代码并启用。返回填充条数。
     */
    public static function seedTestSpots(): int
    {
        static::query()->delete();

        $count = 0;

        foreach (config('nova-admin.ad_positions', []) as $position => $label) {
            $isHead = $position === 'global_head';

            static::query()->create([
                'position'  => $position,
                'head_code' => $isHead
                    ? static::testHeadScript()
                    : "<!-- [测试广告] {$label} head 代码 -->\n<script>console.log('[测试广告] {$label} head 已加载');</script>",
                'body_code' => $isHead
                    ? null
                    : "<div style=\"padding:20px;background:#ffcc00;border:2px dashed #333;color:#000;font-weight:bold;\">\n    [测试广告] {$label}\n</div>",
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
        return static::query()->update(['is_active' => false]);
    }

    private static function testHeadScript(): string
    {
        return <<<HTML
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0000000000000000" crossorigin="anonymous"></script>
        <script>console.log('[测试广告] 全局 Head script 已加载');</script>
        HTML;
    }
}
