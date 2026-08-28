<?php

namespace Inova\NovaAdmin\Services;

use Inova\NovaAdmin\Models\AdSpot;

class AdService
{
    /** @var array<string, array{head_code: ?string, body_code: ?string}>|null */
    protected ?array $activeSpots = null;

    /** 广告位有写入时必须调用，否则同一请求内会读到旧代码。 */
    public function flush(): void
    {
        $this->activeSpots = null;
    }

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

        // 一个页面通常要取多个广告位，逐位查询会打出 N 条 SQL；请求内一次查全再缓存。
        if ($this->activeSpots === null) {
            $this->activeSpots = AdSpot::query()
                ->where('is_active', true)
                ->get(['position', 'head_code', 'body_code'])
                ->keyBy('position')
                ->map(fn (AdSpot $spot) => [
                    'head_code' => $spot->head_code,
                    'body_code' => $spot->body_code,
                ])
                ->all();
        }

        return trim((string) ($this->activeSpots[$position][$column] ?? ''));
    }
}
