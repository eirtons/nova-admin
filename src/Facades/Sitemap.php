<?php

namespace Nbutl\NovaAdmin\Facades;

use Illuminate\Support\Facades\Facade;
use Nbutl\NovaAdmin\Services\SitemapService;

/**
 * @method static void register(callable $provider)
 * @method static string xml()
 * @method static string build()
 * @method static void forget()
 *
 * @see \Nbutl\NovaAdmin\Services\SitemapService
 */
class Sitemap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SitemapService::class;
    }
}
