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
    ) {
        $this->html = $ads->body($position);
    }

    public function shouldRender(): bool
    {
        return trim($this->html) !== '';
    }

    public function render(): HtmlString
    {
        return new HtmlString(view('nova-admin::components.ad-slot', [
            'html' => $this->html,
            'position' => $this->position,
        ])->render());
    }
}
