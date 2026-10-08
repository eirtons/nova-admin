<?php

namespace Inova\NovaAdmin\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 清 Cloudflare 边缘缓存。
 *
 * CacheablePage 让前台页面带 s-maxage 进了边缘缓存，后台改广告、站点设置、静态页之后，
 * 边缘副本还是旧的，要等 s-maxage 过期（最长到当天结束）。项目自己的批量改动
 * （切换领域、删内容、换主题）更糟：旧页面链着已删除的内容，回源就是 404，404 也会被边缘缓存。
 * Laravel 的缓存清理碰不到 CDN，只能调 Cloudflare API。
 *
 * - 只清本站 host（取 APP_URL）：同一个 zone 下常挂着多个站点，purge_everything 会连带清掉别人的。
 * - 没配 zone_id 时按域名逐级向 API 查，省得每个站去后台抄 Zone ID。
 * - 本地与测试环境不清：APP_URL 不是线上域名，跑测试 / seeder 也不该真的去调 Cloudflare。
 * - 清缓存失败只记日志，不能打断后台主流程。
 */
class EdgeCacheService
{
    private const API = 'https://api.cloudflare.com/client/v4';

    /** @var array<int, string> 待清的原因，请求 / 命令 / 队列任务结束时合并清一次 */
    protected array $pending = [];

    /**
     * 登记一次待清，在请求 / 命令 / 队列任务结束时合并执行。
     * 后台一次保存常写多条记录（站点设置逐字段写、webdeploy 下发多个广告位），
     * 每条都调 API 会撞上 Cloudflare 的 purge 频率限制，也拖慢后台响应。
     */
    public function purgeLater(string $reason): void
    {
        if (! in_array($reason, $this->pending, true)) {
            $this->pending[] = $reason;
        }
    }

    /** 执行登记过的待清；没有待清时什么都不做。 */
    public function flushPending(): bool
    {
        if ($this->pending === []) {
            return false;
        }

        $reason = implode(',', $this->pending);
        $this->pending = [];

        return $this->purge($reason);
    }

    public function purge(string $reason): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        $token = (string) config('nova-admin.cloudflare.api_token');
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($token === '' || $host === '') {
            Log::info('未配置 CLOUDFLARE_API_TOKEN 或 APP_URL，跳过清边缘缓存', ['reason' => $reason]);

            return false;
        }

        try {
            $zoneId = $this->zoneId($token, $host);

            if ($zoneId === null) {
                Log::warning('Cloudflare 账号下找不到本站 zone，跳过清边缘缓存', ['reason' => $reason, 'host' => $host]);

                return false;
            }

            $response = Http::withToken($token)
                ->timeout(10)
                ->post(self::API."/zones/{$zoneId}/purge_cache", ['hosts' => [$host]]);

            if ($response->successful() && $response->json('success') === true) {
                Log::info('已清空 Cloudflare 边缘缓存', ['reason' => $reason, 'host' => $host]);

                return true;
            }

            Log::warning('清 Cloudflare 边缘缓存失败', [
                'reason' => $reason,
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('清 Cloudflare 边缘缓存异常', ['reason' => $reason, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * 优先用 CLOUDFLARE_ZONE_ID；没配就按 host 逐级去掉子域名查（a.example.com → example.com）。
     * 查到的才缓存，查不到下次重试。
     */
    protected function zoneId(string $token, string $host): ?string
    {
        $configured = (string) config('nova-admin.cloudflare.zone_id');

        if ($configured !== '') {
            return $configured;
        }

        return Cache::rememberForever("nova-admin:cloudflare-zone:{$host}", function () use ($token, $host) {
            $labels = explode('.', $host);

            while (count($labels) >= 2) {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get(self::API.'/zones', ['name' => implode('.', $labels)]);

                $id = $response->json('result.0.id');

                if ($response->successful() && is_string($id) && $id !== '') {
                    return $id;
                }

                array_shift($labels);
            }

            return null;
        });
    }
}
