<?php

namespace Inova\NovaAdmin\Filament\Resources\StaticPageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Inova\NovaAdmin\Filament\Resources\StaticPageResource;

class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
