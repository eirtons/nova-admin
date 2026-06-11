<?php

namespace Nbutl\NovaAdmin\Filament\Resources\AdSpotResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Nbutl\NovaAdmin\Filament\Resources\AdSpotResource;

class ListAdSpots extends ListRecords
{
    protected static string $resource = AdSpotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
