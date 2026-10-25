<?php

namespace App\Filament\Budget\Resources\TiersRecetteResource\Pages;

use App\Filament\Budget\Resources\TiersRecetteResource;
use Filament\Resources\Pages\ListRecords;

class ListTiersRecettes extends ListRecords
{
    protected static string $resource = TiersRecetteResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Nouveau débiteur / payeur')];
    }
}
