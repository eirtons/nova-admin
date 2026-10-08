<?php

namespace Inova\NovaAdmin\Console\Commands;

use Illuminate\Console\Command;
use Inova\NovaAdmin\Services\EdgeCacheService;

class PurgeEdgeCacheCommand extends Command
{
    protected $signature = 'nova-admin:purge-edge-cache';

    protected $description = '清除本站（APP_URL 的 host）在 Cloudflare 边缘的缓存';

    public function handle(EdgeCacheService $edgeCache): int
    {
        if ($edgeCache->purge('artisan')) {
            $this->info('已清除 Cloudflare 边缘缓存。');

            return self::SUCCESS;
        }

        $this->warn('未清除：本地 / 测试环境、未配置 CLOUDFLARE_API_TOKEN，或 Cloudflare 返回失败，详见日志。');

        return self::FAILURE;
    }
}
