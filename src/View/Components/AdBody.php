<?php

namespace Nbutl\NovaAdmin\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Nbutl\NovaAdmin\Services\AdService;

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
        return $this->html !== '';
    }

    public function render(): HtmlString
    {
        return new HtmlString(
            '<div style="width: 100% !important; text-align: center !important; margin: 10px auto !important; overflow: hidden !important;">'
            .$this->html
            .'</div>'
        );
    }
}
