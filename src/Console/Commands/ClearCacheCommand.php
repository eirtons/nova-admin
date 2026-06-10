<?php

namespace Nbutl\NovaSiteCore\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaSiteCore\Services\AdService;

class ClearCacheCommand extends Command
{
    protected $signature = 'nova-site-core:clear-cache';

    protected $description = '清除所有广告位缓存';

    public function handle(AdService $ads): int
    {
        foreach (array_keys(config('nova-site-core.ad_positions', [])) as $position) {
            $ads->forgetPosition($position);
        }

        $this->info('广告缓存已清除。');

        return self::SUCCESS;
    }
}
