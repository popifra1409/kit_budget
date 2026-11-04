<?php

namespace App\Filament\Marches\Resources\TypeOrdreServiceResource\Pages;

use App\Filament\Marches\Resources\TypeOrdreServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypesOrdreService extends ListRecords
{
    protected static string $resource = TypeOrdreServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
