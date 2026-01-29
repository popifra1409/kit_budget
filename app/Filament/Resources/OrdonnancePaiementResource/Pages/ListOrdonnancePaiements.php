<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrdonnancePaiements extends ListRecords
{
    protected static string $resource = OrdonnancePaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
