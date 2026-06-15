<?php

namespace Nbutl\NovaAdmin\Services;

use Nbutl\NovaAdmin\Models\AdSpot;

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

        return AdSpot::query()
            ->where('position', $position)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck($column)
            ->map(fn ($code): string => (string) $code)
            ->filter(fn (string $code): bool => trim($code) !== '')
            ->implode("\n");
    }
}
