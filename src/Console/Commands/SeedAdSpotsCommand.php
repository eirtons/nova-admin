<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Inova\NovaAdmin\Models\AdSpot;

class SeedAdSpotsCommand extends Command
{
    protected $signature = 'ad:seed {--off : 仅禁用所有广告，不填充}';

    protected $description = '清空并填充全部广告位的测试代码并启用，方便开发调试';

    public function handle(): int
    {
        $model = AdSpot::class;

        if ($this->option('off')) {
            $count = $model::query()->update(['is_active' => false]);
            $this->info("已禁用 {$count} 条广告。");

            return self::SUCCESS;
        }

        // 每次执行先清空，避免脏数据与重复位置
        $model::query()->delete();

        foreach (config('nova-admin.ad_positions', []) as $position => $label) {
            $isHead = $position === 'global_head';

            $model::query()->create([
                'position'  => $position,
                'head_code' => $isHead ? $this->headScript() : $this->headPlaceholder($label),
                'body_code' => $isHead ? null : $this->placeholder($label),
                'is_active' => true,
            ]);

            $this->line("✓ {$label}");
        }

        $this->info('广告测试代码已填充并启用。');

        return self::SUCCESS;
    }

    private function headScript(): string
    {
        return <<<HTML
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-0000000000000000" crossorigin="anonymous"></script>
        <script>console.log('[测试广告] 全局 Head script 已加载');</script>
        HTML;
    }

    private function headPlaceholder(string $label): string
    {
        return <<<HTML
        <!-- [测试广告] {$label} head 代码 -->
        <script>console.log('[测试广告] {$label} head 已加载');</script>
        HTML;
    }

    private function placeholder(string $label): string
    {
        return <<<HTML
        <div style="padding:20px;background:#ffcc00;border:2px dashed #333;color:#000;font-weight:bold;">
            [测试广告] {$label}
        </div>
        HTML;
    }
}
