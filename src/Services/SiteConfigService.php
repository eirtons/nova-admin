<?php

namespace Inova\NovaAdmin\Services;

use Inova\NovaAdmin\Models\SiteConfig;
use Illuminate\Database\QueryException;

class SiteConfigService
{
    /** @var array<string, mixed> */
    protected array $resolved = [];

    public function get(string $key, mixed $default = null): mixed
    {
        // 单次请求里 site_name / meta 之类会被反复读取，命中即复用，避免重复查表。
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        try {
            $row = SiteConfig::query()->where('key', $key)->first();
        } catch (QueryException) {
            // 首次部署迁移前，前台 helper 也应能用配置默认值正常渲染。
            return $this->resolved[$key] = $default;
        }

        if ($row === null) {
            return $this->resolved[$key] = $default;
        }

        return $this->resolved[$key] = $this->cast($row->value, $row->type);
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

        unset($this->resolved[$key]);
    }

    public function forget(string $key): void
    {
        SiteConfig::query()->where('key', $key)->delete();

        unset($this->resolved[$key]);
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
