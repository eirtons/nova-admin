<?php

namespace Inova\NovaAdmin\Filament\Resources\AdSpotResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Inova\NovaAdmin\Filament\Resources\AdSpotResource;

class CreateAdSpot extends CreateRecord
{
    protected static string $resource = AdSpotResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
