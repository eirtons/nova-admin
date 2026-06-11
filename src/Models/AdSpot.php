<?php

namespace Nbutl\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Nbutl\NovaAdmin\Services\AdService;

class AdSpot extends Model
{
    protected $table = 'ad_spots';

    protected $fillable = [
        'position',
        'head_code',
        'body_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // 广告保存 / 删除后清除受影响 position 的缓存
        static::saved(function (self $model) {
            app(AdService::class)->forgetPosition($model->position);

            if ($model->wasChanged('position')) {
                app(AdService::class)->forgetPosition($model->getOriginal('position'));
            }
        });

        static::deleted(function (self $model) {
            app(AdService::class)->forgetPosition($model->position);
        });
    }
}
