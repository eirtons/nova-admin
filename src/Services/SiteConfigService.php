<?php

namespace Nbutl\NovaSiteCore\Services;

class SiteConfigService
{
    /**
     * 按 type 转换后返回配置值；key 不存在返回 $default，不抛异常。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $model = $this->modelClass();
        $row = $model::query()->where('key', $key)->first();

        if ($row === null) {
            return $default;
        }

        return $this->cast($row->value, $row->type);
    }

    /**
     * 按 type 序列化入库；未知 key 默认以 type=string 新建。
     */
    public function set(string $key, mixed $value, ?string $type = null, ?string $group = null): void
    {
        $model = $this->modelClass();
        $existing = $model::query()->where('key', $key)->first();

        $type = $type ?? $existing?->type ?? $this->inferType($value);

        $model::query()->updateOrCreate(
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
        $this->modelClass()::query()->where('key', $key)->delete();
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
            default   => $value, // string | text
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

    protected function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value)             => 'boolean',
            is_int($value)              => 'integer',
            is_array($value)            => 'json',
            default                     => 'string',
        };
    }

    protected function modelClass(): string
    {
        return config('nova-site-core.models.site_config', \Nbutl\NovaSiteCore\Models\SiteConfig::class);
    }
}
