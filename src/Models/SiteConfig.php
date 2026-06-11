<?php

namespace Nbutl\NovaAdmin\Models;

use Illuminate\Database\Eloquent\Model;

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
}
