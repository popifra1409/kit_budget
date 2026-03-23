<?php

namespace App\Filament\Budget\Resources\ExerciceResource\Pages;

use App\Filament\Budget\Resources\ExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExercices extends ListRecords
{
    protected static string $resource = ExerciceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
