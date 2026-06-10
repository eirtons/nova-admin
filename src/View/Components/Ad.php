<?php

namespace Nbutl\NovaSiteCore\View\Components;

use Illuminate\View\Component;
use Nbutl\NovaSiteCore\Services\AdService;

class Ad extends Component
{
    public function __construct(public string $position) {}

    public function render(): string
    {
        return app(AdService::class)->renderBody($this->position);
    }
}
