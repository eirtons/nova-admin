<?php

namespace Inova\NovaAdmin\Facades;

use Illuminate\Support\Facades\Facade;
use Inova\NovaAdmin\Services\SitemapService;

/**
 * @method static void register(callable $provider, int $priority = 0)
 * @method static string xml()
 * @method static string build()
 * @method static int rebuild()
 * @method static void forget()
 * @method static ?string cachedXml()
 * @method static string url()
 *
 * @see \Inova\NovaAdmin\Services\SitemapService
 */
class Sitemap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SitemapService::class;
    }
}
