<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inova\NovaAdmin\Services\AdService;

class AdBody extends Component
{
    public string $html;

    public function __construct(
        public string $position,
        AdService $ads,
        // anchor / interstitial 这类浮层广告自己定位，套居中容器会破坏其布局。
        public bool $wrapper = true,
    ) {
        $this->html = $ads->body($position);
    }

    public function shouldRender(): bool
    {
        return trim($this->html) !== '';
    }

    public function render(): HtmlString
    {
        if (! $this->wrapper) {
            return new HtmlString($this->html);
        }

        return new HtmlString(view('nova-admin::components.ad-slot', [
            'html' => $this->html,
            'position' => $this->position,
        ])->render());
    }
}
