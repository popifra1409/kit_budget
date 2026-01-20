<?php

namespace App\Filament\Resources\RecetteReelleResource\Pages;

use App\Filament\Resources\RecetteReelleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecetteReelles extends ListRecords
{
    protected static string $resource = RecetteReelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
