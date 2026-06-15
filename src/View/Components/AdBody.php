<?php

namespace Nbutl\NovaAdmin\View\Components;

use Illuminate\Contracts\View\View;
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

    public function render(): View
    {
        return view('nova-admin::components.ad-body');
    }
}
