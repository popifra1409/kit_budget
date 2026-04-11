<?php

namespace App\Filament\Budget\Resources\ExerciceResource\Pages;

use App\Filament\Budget\Resources\ExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExercice extends EditRecord
{
    protected static string $resource = ExerciceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
