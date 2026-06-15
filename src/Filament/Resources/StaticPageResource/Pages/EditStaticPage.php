<?php

namespace Nbutl\NovaAdmin\Filament\Resources\StaticPageResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Nbutl\NovaAdmin\Filament\Resources\StaticPageResource;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
