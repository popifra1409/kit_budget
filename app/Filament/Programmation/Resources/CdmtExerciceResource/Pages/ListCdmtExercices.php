<?php

namespace App\Filament\Programmation\Resources\CdmtExerciceResource\Pages;

use App\Filament\Programmation\Resources\CdmtExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCdmtExercices extends ListRecords
{
    protected static string $resource = CdmtExerciceResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
