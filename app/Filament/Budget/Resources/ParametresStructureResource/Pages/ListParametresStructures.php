<?php

namespace App\Filament\Budget\Resources\ParametresStructureResource\Pages;

use App\Filament\Budget\Resources\ParametresStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParametresStructures extends ListRecords
{
    protected static string $resource = ParametresStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
