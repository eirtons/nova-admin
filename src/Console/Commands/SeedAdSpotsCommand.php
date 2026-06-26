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
        if ($this->option('off')) {
            $count = AdSpot::deactivateAll();
            $this->info("已禁用 {$count} 条广告。");

            return self::SUCCESS;
        }

        $count = AdSpot::seedTestSpots();
        $this->info("广告测试代码已填充并启用（{$count} 条）。");

        return self::SUCCESS;
    }
}
