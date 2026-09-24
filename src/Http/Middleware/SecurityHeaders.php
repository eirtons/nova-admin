<?php

namespace Inova\NovaAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HSTS：告诉浏览器此后一律用 HTTPS 访问本站，省掉 http→https 的那一次跳转。
 *
 * 只在请求本身已经是 HTTPS 时下发（HSTS 在明文响应上会被浏览器忽略），
 * 所以本地 http 开发不受影响。不带 includeSubDomains——子域未必都上了 HTTPS。
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('nova-admin.security.hsts')
            && $request->isSecure()
            && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.(int) config('nova-admin.security.hsts_max_age', 31536000),
            );
        }

        return $response;
    }
}
