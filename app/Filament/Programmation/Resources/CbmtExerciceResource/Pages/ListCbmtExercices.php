<?php

namespace App\Filament\Programmation\Resources\CbmtExerciceResource\Pages;

use App\Filament\Programmation\Resources\CbmtExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCbmtExercices extends ListRecords
{
    protected static string $resource = CbmtExerciceResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
