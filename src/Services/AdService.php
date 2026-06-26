<?php

namespace Inova\NovaAdmin\Services;

use Inova\NovaAdmin\Models\AdSpot;

class AdService
{
    public function body(string $position): string
    {
        return $this->code($position, 'body_code');
    }

    public function head(string $position): string
    {
        return $this->code($position, 'head_code');
    }

    protected function code(string $position, string $column): string
    {
        if (! array_key_exists($position, (array) config('nova-admin.ad_positions', []))) {
            return '';
        }

        return (string) AdSpot::query()
            ->where('position', $position)
            ->where('is_active', true)
            ->value($column);
    }
}
