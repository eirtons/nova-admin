<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inova\NovaAdmin\Services\AdService;

/**
 * 布局 </body> 前的广告：全部布局级位与 global_head 的 body 部分。
 * 这些位自己 position:fixed 或只是脚本，一律不套居中容器。
 */
class AdLayoutBody extends Component
{
    public string $html;

    public function __construct(AdService $ads, public bool $enabled = true)
    {
        $this->html = implode("\n", array_filter(array_map(
            fn (string $position) => $ads->body($position),
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
