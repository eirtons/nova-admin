<?php

namespace Inova\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inova\NovaAdmin\Services\AdService;

class AdHead extends Component
{
    public string $html;

    public function __construct(
        public string $position,
        AdService $ads,
    ) {
        $this->html = $ads->head($position);
    }

    public function shouldRender(): bool
    {
        return $this->html !== '';
    }

    public function render(): HtmlString
    {
        return new HtmlString($this->html);
    }
}
