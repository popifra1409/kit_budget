<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegiesAvances extends ListRecords
{
    protected static string $resource = RegieAvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvelle Régie d\'Avance'),
        ];
    }
}
