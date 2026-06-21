<?php

namespace App\Filament\Comptable\Resources\UniteMesureResource\Pages;

use App\Filament\Comptable\Resources\UniteMesureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUniteMesures extends ListRecords
{
    protected static string $resource = UniteMesureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
