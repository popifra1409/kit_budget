<?php

namespace App\Filament\Budget\Resources\ClotureExerciceResource\Pages;

use App\Filament\Budget\Resources\ClotureExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCloturesExercice extends ListRecords
{
    protected static string $resource = ClotureExerciceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Préparer une clôture')];
    }
}
