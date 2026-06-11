<?php

namespace Nbutl\NovaAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(config('nova-admin.locale', 'zh_CN'));

        return $next($request);
    }
}
