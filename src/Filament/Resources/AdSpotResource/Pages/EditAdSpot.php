<?php

namespace Nbutl\NovaAdmin\Filament\Resources\AdSpotResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Nbutl\NovaAdmin\Filament\Resources\AdSpotResource;

class EditAdSpot extends EditRecord
{
    protected static string $resource = AdSpotResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
