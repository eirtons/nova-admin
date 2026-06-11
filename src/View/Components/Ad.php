<?php

namespace Nbutl\NovaAdmin\View\Components;

use Illuminate\View\Component;
use Nbutl\NovaAdmin\Services\AdService;

class Ad extends Component
{
    public function __construct(public string $position) {}

    public function render(): string
    {
        return app(AdService::class)->renderBody($this->position);
    }
}
