<?php

namespace Inova\NovaAdmin\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 生成 sitemap.xml：config 静态条目 + 项目注册的 URL Provider。
 *
 * 条目格式：['loc' => '/about' 或绝对 URL, 'lastmod' => DateTimeInterface|string|null,
 *           'changefreq' => ?string, 'priority' => ?string]；也可直接传 loc 字符串。
 */
class SitemapService
{
    /** @var array<callable(): iterable<array|string>> */
    protected array $providers = [];

    /**
     * 项目在 ServiceProvider::boot 中注册动态 URL 来源，返回条目集合（见类注释）。
     */
    public function register(callable $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * 带缓存的 XML（/sitemap.xml 路由使用）。
     */
    public function xml(): string
    {
        $ttl = (int) config('nova-admin.sitemap.cache_ttl', 1800);

        if ($ttl <= 0) {
            return $this->build();
        }

        return $this->store()->remember($this->cacheKey(), $ttl, fn () => $this->build());
    }

    public function build(): string
    {
        $entries = (array) config('nova-admin.sitemap.urls', []);

        foreach ($this->providers as $provider) {
            foreach ($provider() as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->render(array_map($this->normalize(...), $entries));
    }

    public function forget(): void
    {
        $this->store()->forget($this->cacheKey());
    }

    protected function normalize(array|string $entry): array
    {
        if (is_string($entry)) {
            $entry = ['loc' => $entry];
        }

        $loc = (string) ($entry['loc'] ?? '/');
        if (! preg_match('#^https?://#', $loc)) {
            $loc = url($loc);
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

    protected function render(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($u['loc'])."</loc>\n";

            foreach (['lastmod', 'changefreq', 'priority'] as $tag) {
                if (! blank($u[$tag])) {
                    $xml .= "    <{$tag}>".e((string) $u[$tag])."</{$tag}>\n";
                }
            }

            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>';
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
