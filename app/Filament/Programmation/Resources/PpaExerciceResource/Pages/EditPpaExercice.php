<?php

namespace App\Filament\Programmation\Resources\PpaExerciceResource\Pages;

use App\Filament\Programmation\Resources\PpaExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPpaExercice extends EditRecord
{
    protected static string $resource = PpaExerciceResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
