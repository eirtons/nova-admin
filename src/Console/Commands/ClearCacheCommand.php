<?php

namespace Nbutl\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Nbutl\NovaAdmin\Services\SitemapService;

class ClearCacheCommand extends Command
{
    protected $signature = 'nova-admin:clear-sitemap-cache';

    protected $description = '清除 sitemap 缓存';

    public function handle(SitemapService $sitemap): int
    {
        $sitemap->forget();

        $this->info('sitemap 缓存已清除。');

        return self::SUCCESS;
    }
}
