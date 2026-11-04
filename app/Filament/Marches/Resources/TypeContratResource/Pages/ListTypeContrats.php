<?php

namespace App\Filament\Marches\Resources\TypeContratResource\Pages;

use App\Filament\Marches\Resources\TypeContratResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypeContrats extends ListRecords
{
    protected static string $resource = TypeContratResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
