<?php

use Inova\NovaAdmin\Models\StaticPage;
use Inova\NovaAdmin\Services\AdService;
use Inova\NovaAdmin\Services\SiteConfigService;

if (! function_exists('site_config')) {
    function site_config(string $key, mixed $default = null): mixed
    {
        return app(SiteConfigService::class)->get($key, $default);
    }
}

if (! function_exists('site_setting')) {
    /** 后台「站点设置」的值：未保存过时回退 nova-admin.site_defaults，与设置页预填一致。 */
    function site_setting(string $key): mixed
    {
        return site_config($key) ?? config("nova-admin.site_defaults.$key");
    }
}

if (! function_exists('site_media_url')) {
    /** 站点设置里上传的媒体（favicon_path / logo_path）的访问 URL，未设置返回 null。 */
    function site_media_url(string $key): ?string
    {
        $path = site_setting($key);

        if (blank($path)) {
            return null;
        }

        return str_starts_with($path, 'http') || str_starts_with($path, '/')
            ? $path
            : asset('storage/'.$path);
    }
}

if (! function_exists('site_ad')) {
    function site_ad(string $position): string
    {
        return app(AdService::class)->body($position);
    }
}

if (! function_exists('site_ad_head')) {
    function site_ad_head(string $position): string
    {
        return app(AdService::class)->head($position);
    }
}

if (! function_exists('static_page')) {
    function static_page(string $slug): ?StaticPage
    {
        return StaticPage::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }
}
