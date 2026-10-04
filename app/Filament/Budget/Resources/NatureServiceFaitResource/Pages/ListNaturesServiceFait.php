<?php

namespace App\Filament\Budget\Resources\NatureServiceFaitResource\Pages;

use App\Filament\Budget\Resources\NatureServiceFaitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNaturesServiceFait extends ListRecords
{
    protected static string $resource = NatureServiceFaitResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
