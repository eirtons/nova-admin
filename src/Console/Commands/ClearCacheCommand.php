<?php

namespace Nbutl\NovaSiteCore\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaSiteCore\Services\AdService;
use Nbutl\NovaSiteCore\Services\SitemapService;

class ClearCacheCommand extends Command
{
    protected $signature = 'nova-site-core:clear-cache';

    protected $description = '清除所有广告位与 sitemap 缓存';

    public function handle(AdService $ads, SitemapService $sitemap): int
    {
        foreach (array_keys(config('nova-site-core.ad_positions', [])) as $position) {
            $ads->forgetPosition($position);
        }

        $sitemap->forget();

        $this->info('广告与 sitemap 缓存已清除。');

        return self::SUCCESS;
    }
}
