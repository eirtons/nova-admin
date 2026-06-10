<?php

namespace Nbutl\NovaSiteCore\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AdService
{
    /**
     * 取某广告位下所有启用广告（按 id 升序），带缓存。
     */
    public function forPosition(string $position): Collection
    {
        $ttl = (int) config('nova-site-core.cache.ttl', 3600);

        if ($ttl <= 0) {
            return $this->query($position);
        }

        return $this->store()->remember(
            $this->key($position),
            $ttl,
            fn () => $this->query($position)
        );
    }

    /**
     * 输出某广告位的 body 代码（拼接多条启用广告）。
     */
    public function renderBody(string $position): string
    {
        if (! $this->isKnownPosition($position)) {
            return $this->unknownPositionHint($position);
        }

        return $this->forPosition($position)
            ->map(fn ($ad) => (string) $ad->body_code)
            ->filter()
            ->implode("\n");
    }

    /**
     * 输出某广告位的 head 代码。
     */
    public function renderHead(string $position): string
    {
        if (! $this->isKnownPosition($position)) {
            return $this->unknownPositionHint($position);
        }

        return $this->forPosition($position)
            ->map(fn ($ad) => (string) $ad->head_code)
            ->filter()
            ->implode("\n");
    }

    public function forgetPosition(?string $position): void
    {
        if ($position === null || $position === '') {
            return;
        }

        $this->store()->forget($this->key($position));
    }

    protected function query(string $position): Collection
    {
        $model = config('nova-site-core.models.ad_spot', \Nbutl\NovaSiteCore\Models\AdSpot::class);

        return $model::query()
            ->where('position', $position)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    protected function isKnownPosition(string $position): bool
    {
        return array_key_exists($position, (array) config('nova-site-core.ad_positions', []));
    }

    protected function unknownPositionHint(string $position): string
    {
        if (config('app.debug')) {
            return '<!-- nova-site-core: unknown ad position "'.e($position).'" -->';
        }

        return '';
    }

    protected function key(string $position): string
    {
        return config('nova-site-core.cache.key_prefix', 'nova_site_core:ads:').$position;
    }

    protected function store()
    {
        return Cache::store(config('nova-site-core.cache.store'));
    }
}
