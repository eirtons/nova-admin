<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Inova\NovaAdmin\Services\EdgeCacheService;
use Inova\NovaAdmin\Services\SitemapService;

class BuildSitemapCommand extends Command
{
    protected $signature = 'nova-admin:build-sitemap';

    protected $description = '加锁重建 sitemap.xml 缓存并按需刷新边缘缓存';

    public function handle(SitemapService $sitemap, EdgeCacheService $edgeCache): int
    {
        $previous = $sitemap->cachedXml();
        $count = $sitemap->rebuild();
        $current = $sitemap->cachedXml();

        // 内容确实变化或原缓存为空时，仅清 /sitemap.xml 单个 URL，不波及全站页面缓存
        if ($previous === null || $current === null || $previous !== $current) {
            $edgeCache->purgeUrls([$sitemap->url()], 'sitemap');
        }

        $this->info("sitemap 已重建，共 {$count} 个 URL。");

        return self::SUCCESS;
    }
}
