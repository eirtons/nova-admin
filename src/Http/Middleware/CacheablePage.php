<?php

namespace Inova\NovaAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 把包自己注册的前台响应标记为可被浏览器与 CDN 缓存。
 *
 * 这些路由刻意不挂 web 组：带 Cookie 的响应 Cloudflare 一律按 DYNAMIC 处理、
 * 每次回源，去掉会话后配合这里的 Cache-Control，边缘才能真正命中。
 *
 * TTL 优先跟随宿主的 config/page-cache.php（若项目做过前台缓存改造），
 * 没有该文件时用包内默认值，老项目不必新增配置也能生效。
 */
class CacheablePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ttl = (int) config('page-cache.ttl', config('nova-admin.page_cache.ttl', 600));
        $cdnTtl = (int) config('page-cache.cdn_ttl', config('nova-admin.page_cache.cdn_ttl', 86400));

        // 只缓存正常的 GET/HEAD 成功响应；重定向与错误页不缓存
        if ($ttl <= 0 || ! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        // 带 Cookie 的响应在 CDN 上不可共享缓存，宁可不缓存也不能串号
        if (count($response->headers->getCookies()) > 0) {
            return $response;
        }

        // 路由已经自己定过缓存时长的就不要覆盖
        if ($response->headers->hasCacheControlDirective('max-age')) {
            return $response;
        }

        // 边缘副本不跨自然日：页面上常有随日期变化的内容，跨天的副本会把昨天的带到今天。
        // 留 5 分钟下限，避免临近午夜时 TTL 掉到几秒。
        $cdnTtl = min($cdnTtl, max(300, (int) now()->diffInSeconds(now()->endOfDay())));

        $response->headers->set(
            'Cache-Control',
            sprintf('public, max-age=%d, s-maxage=%d, stale-while-revalidate=60', $ttl, $cdnTtl)
        );

        return $response;
    }
}
