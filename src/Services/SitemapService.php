<?php

namespace Inova\NovaAdmin\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 生成 sitemap.xml：config 静态条目 + 项目注册的 URL Provider。
 *
 * 条目格式：['loc' => '/about' 或绝对 URL, 'lastmod' => DateTimeInterface|string|null,
 *           'changefreq' => ?string, 'priority' => ?string]；也可直接传 loc 字符串。
 */
class SitemapService
{
    public const MAX_URLS = 50000;

    public const MAX_BYTES = 52428800; // 50 MiB（Sitemaps 协议单文件上限）

    /** 包内静态页（法务页）默认优先级：排在宿主默认 priority=0 的业务内容之后。 */
    public const STATIC_PAGES_PRIORITY = -100;

    /** @var array<int, array{provider: callable(): iterable<array|string>, priority: int, order: int}> */
    protected array $providers = [];

    /**
     * 项目在 ServiceProvider::boot 中注册动态 URL 来源，返回条目集合（见类注释）。
     * priority 越大越靠前；同优先级保持注册顺序。
     */
    public function register(callable $provider, int $priority = 0): void
    {
        $this->providers[] = [
            'provider' => $provider,
            'priority' => $priority,
            'order'    => count($this->providers),
        ];
    }

    /**
     * 带缓存与并发锁的 XML（/sitemap.xml 路由使用）。
     */
    public function xml(): string
    {
        $ttl = $this->cacheTtl();

        if ($ttl <= 0) {
            return $this->build();
        }

        $cached = $this->cachedXml();
        if ($cached !== null) {
            return $cached;
        }

        try {
            return $this->withBuildLock(function () use ($ttl): string {
                $cached = $this->cachedXml();
                if ($cached !== null) {
                    return $cached;
                }

                $xml = $this->build();
                $this->store()->put($this->cacheKey(), $xml, $ttl);

                return $xml;
            });
        } catch (LockTimeoutException) {
            return $this->cachedXml() ?? $this->build();
        }
    }

    public function build(): string
    {
        return $this->generate(strictLimit: false)['xml'];
    }

    /**
     * 加锁重建并原子覆盖缓存（不先 forget，避免缓存空窗），返回 URL 条数。
     * 超出单文件协议上限时抛异常，原有缓存保持不变。
     */
    public function rebuild(): int
    {
        return $this->withBuildLock(function (): int {
            ['xml' => $xml, 'count' => $count] = $this->generate(strictLimit: true);

            $ttl = $this->cacheTtl();
            if ($ttl > 0) {
                $this->store()->put($this->cacheKey(), $xml, $ttl);
            }

            return $count;
        });
    }

    public function forget(): void
    {
        $this->store()->forget($this->cacheKey());
    }

    public function cachedXml(): ?string
    {
        $cached = $this->store()->get($this->cacheKey());

        return is_string($cached) ? $cached : null;
    }

    /** 基于 APP_URL 生成 sitemap.xml 的完整地址（与 CLI / 请求域名无关）。 */
    public function url(): string
    {
        $appUrl = $this->appUrl();

        return $appUrl !== null ? $appUrl.'/sitemap.xml' : url('/sitemap.xml');
    }

    /**
     * @return array{xml: string, count: int}
     */
    protected function generate(bool $strictLimit): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        $closing = "</urlset>\n";
        $maxUrls = $this->maxUrls();
        $maxBytes = $this->maxBytes();

        $seen = [];
        $count = 0;

        foreach ($this->iterateEntries() as $entry) {
            $u = $this->normalize($entry);
            if ($u === null || isset($seen[$u['loc']])) {
                continue;
            }

            $line = $this->formatUrl($u);
            $exceedsUrls = $count >= $maxUrls;
            $exceedsBytes = (strlen($xml) + strlen($line) + strlen($closing)) > $maxBytes;

            if ($exceedsUrls || $exceedsBytes) {
                if ($strictLimit) {
                    $reason = $exceedsUrls
                        ? "URL 数量超过单文件协议上限 {$maxUrls}"
                        : "体积超过单文件协议上限 {$maxBytes} 字节";

                    throw new \RuntimeException("Sitemap {$reason}，请拆分或缩减范围。");
                }

                Log::warning('Sitemap 超过单文件协议上限，已截断输出', [
                    'count'     => $count,
                    'max_urls'  => $maxUrls,
                    'max_bytes' => $maxBytes,
                ]);

                break;
            }

            $seen[$u['loc']] = true;
            $xml .= $line;
            $count++;
        }

        return [
            'xml'   => $xml.$closing,
            'count' => $count,
        ];
    }

    protected function iterateEntries(): \Generator
    {
        foreach ((array) config('nova-admin.sitemap.urls', []) as $entry) {
            yield $entry;
        }

        $providers = $this->providers;
        usort(
            $providers,
            fn (array $a, array $b) => ($b['priority'] <=> $a['priority']) ?: ($a['order'] <=> $b['order']),
        );

        foreach ($providers as $item) {
            foreach (($item['provider'])() as $entry) {
                yield $entry;
            }
        }
    }

    /**
     * @return array{loc: string, lastmod: ?string, changefreq: mixed, priority: mixed}|null
     */
    protected function normalize(array|string $entry): ?array
    {
        if (is_string($entry)) {
            $entry = ['loc' => $entry];
        }

        $loc = trim((string) ($entry['loc'] ?? ''));
        if ($loc === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $loc)) {
            $appUrl = $this->appUrl();
            $path = ltrim($loc, '/');
            $loc = $appUrl !== null
                ? ($path === '' ? $appUrl : $appUrl.'/'.$path)
                : url($loc);
        }

        $lastmod = $entry['lastmod'] ?? null;
        if ($lastmod instanceof \DateTimeInterface) {
            $lastmod = $lastmod->format(DATE_ATOM);
        }

        return [
            'loc'        => $loc,
            'lastmod'    => $lastmod,
            'changefreq' => $entry['changefreq'] ?? null,
            'priority'   => $entry['priority'] ?? null,
        ];
    }

    protected function formatUrl(array $u): string
    {
        $line = '<url><loc>'.e($u['loc']).'</loc>';

        foreach (['lastmod', 'changefreq', 'priority'] as $tag) {
            if (! blank($u[$tag])) {
                $line .= "<{$tag}>".e((string) $u[$tag])."</{$tag}>";
            }
        }

        return $line."</url>\n";
    }

    protected function withBuildLock(callable $callback): mixed
    {
        $store = $this->store();

        if (! $store->getStore() instanceof LockProvider) {
            return $callback();
        }

        return $store->lock($this->cacheKey().':lock', 120)->block(15, $callback);
    }

    protected function appUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/');

        return $url !== '' && preg_match('#^https?://#i', $url) ? $url : null;
    }

    protected function maxUrls(): int
    {
        return self::MAX_URLS;
    }

    protected function maxBytes(): int
    {
        return self::MAX_BYTES;
    }

    protected function cacheTtl(): int
    {
        return (int) config('nova-admin.sitemap.cache_ttl', 1800);
    }

    protected function cacheKey(): string
    {
        return (string) config('nova-admin.sitemap.cache_key', 'nova_admin:sitemap');
    }

    protected function store()
    {
        return Cache::store(config('nova-admin.sitemap.cache_store'));
    }
}
