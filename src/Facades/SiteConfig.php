<?php

namespace Nova\NovaAdmin\Facades;

use Illuminate\Support\Facades\Facade;
use Nova\NovaAdmin\Services\SiteConfigService;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value, ?string $type = null, ?string $group = null)
 * @method static void forget(string $key)
 *
 * @see \Nova\NovaAdmin\Services\SiteConfigService
 */
class SiteConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SiteConfigService::class;
    }
}
