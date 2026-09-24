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

    public static function globalHeadPosition(): string
    {
        return (string) config('nova-admin.ads_protocol.global_head_key', 'global_head');
    }

    /** 布局级位（值为真的 ad_layout_positions），不含 global_head。 */
    public static function layoutPositions(): array
    {
        return array_values(array_diff(
            array_keys(array_filter((array) config('nova-admin.ad_layout_positions', []))),
            [static::globalHeadPosition()],
        ));
    }

    /** 布局组件的输出顺序：global_head 必须最后，enabled=false 时只剩它。 */
    public static function layoutRenderOrder(bool $enabled): array
    {
        return [...($enabled ? static::layoutPositions() : []), static::globalHeadPosition()];
    }

    /** 内容位：由页面模板自行放置渲染点的位。 */
    public static function contentPositions(): array
    {
        return array_values(array_diff(
            array_keys((array) config('nova-admin.ad_positions', [])),
            [...static::layoutPositions(), static::globalHeadPosition()],
        ));
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
