<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inova\NovaAdmin\Services\AdService;

/**
 * 布局 <head> 里的广告：先输出全部布局级位（ad_layout_positions），最后输出 global_head。
 * GPT 要求 slot 定义早于 enableServices，所以页面自己 @stack('ad-head') 的内容位
 * 必须写在本组件之前。
 *
 * enabled=false（如 $section->ads_enabled 为 false 的页面）只输出 global_head：
 * 统计、站点验证这类站点级脚本任何页面都要加载。
 */
class AdLayoutHead extends Component
{
    public string $html;

    public function __construct(AdService $ads, public bool $enabled = true)
    {
        $this->html = implode("\n", array_filter(array_map(
            fn (string $position) => $ads->head($position),
            AdService::layoutRenderOrder($enabled),
        )));
    }

    public function shouldRender(): bool
    {
        return trim($this->html) !== '';
    }

    public function render(): HtmlString
    {
        return new HtmlString($this->html);
    }
}
