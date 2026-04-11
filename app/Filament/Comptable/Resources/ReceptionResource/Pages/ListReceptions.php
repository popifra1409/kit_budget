<?php

namespace App\Filament\Comptable\Resources\ReceptionResource\Pages;

use App\Filament\Comptable\Resources\ReceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListReceptions extends ListRecords
{
    protected static string $resource = ReceptionResource::class;
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
