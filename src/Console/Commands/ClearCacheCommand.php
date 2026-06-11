<?php

namespace Nbutl\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaAdmin\Services\AdService;
use Nbutl\NovaAdmin\Services\SitemapService;

class ClearCacheCommand extends Command
{
    protected $signature = 'nova-admin:clear-cache';

    protected $description = '清除所有广告位与 sitemap 缓存';

    public function handle(AdService $ads, SitemapService $sitemap): int
    {
        foreach (array_keys(config('nova-admin.ad_positions', [])) as $position) {
            $ads->forgetPosition($position);
        }

        $sitemap->forget();

        $this->info('广告与 sitemap 缓存已清除。');

        return self::SUCCESS;
    }
}
