<?php

namespace Inova\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Inova\NovaAdmin\Services\EdgeCacheService;

class SiteConfig extends Model
{
    protected $table = 'site_configs';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected static function booted(): void
    {
        // 站点名、SEO、ads.txt / robots.txt 都存在这里，前台整页缓存里带着旧值
        static::saved(fn () => app(EdgeCacheService::class)->purgeLater('site_configs'));
        static::deleted(fn () => app(EdgeCacheService::class)->purgeLater('site_configs'));
    }
}
