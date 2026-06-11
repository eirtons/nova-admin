<?php

use Nbutl\NovaAdmin\Services\AdService;
use Nbutl\NovaAdmin\Services\SiteConfigService;

if (! function_exists('site_config')) {
    function site_config(string $key, mixed $default = null): mixed
    {
        return app(SiteConfigService::class)->get($key, $default);
    }
}

if (! function_exists('site_ad')) {
    function site_ad(string $position): string
    {
        return app(AdService::class)->renderBody($position);
    }
}

if (! function_exists('site_ad_head')) {
    function site_ad_head(string $position): string
    {
        return app(AdService::class)->renderHead($position);
    }
}
