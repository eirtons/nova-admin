<?php

namespace Nbutl\NovaSiteCore\View\Components;

use Illuminate\View\Component;
use Nbutl\NovaSiteCore\Services\AdService;

class AdHead extends Component
{
    public function __construct(public string $position) {}

    public function render(): string
    {
        return app(AdService::class)->renderHead($this->position);
    }
}
