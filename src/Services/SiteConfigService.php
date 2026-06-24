<?php

namespace Nova\NovaAdmin\Services;

use Nova\NovaAdmin\Models\SiteConfig;

class SiteConfigService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = SiteConfig::query()->where('key', $key)->first();

        if ($row === null) {
            return $default;
        }

        return $this->cast($row->value, $row->type);
    }

    public function set(string $key, mixed $value, ?string $type = null, ?string $group = null): void
    {
        $existing = SiteConfig::query()->where('key', $key)->first();

        $type = $type ?? $existing?->type ?? 'string';

        SiteConfig::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serialize($value, $type),
                'type'  => $type,
                'group' => $group ?? $existing?->group,
            ]
        );
    }

    public function forget(string $key): void
    {
        SiteConfig::query()->where('key', $key)->delete();
    }

    protected function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) ((int) $value),
            'integer' => (int) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    protected function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json'    => json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string) $value,
        };
    }
}
