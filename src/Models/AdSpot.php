<?php

namespace Inova\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;

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
}
