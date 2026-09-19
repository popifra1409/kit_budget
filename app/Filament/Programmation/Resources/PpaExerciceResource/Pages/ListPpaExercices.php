<?php

namespace App\Filament\Programmation\Resources\PpaExerciceResource\Pages;

use App\Filament\Programmation\Resources\PpaExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPpaExercices extends ListRecords
{
    protected static string $resource = PpaExerciceResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
