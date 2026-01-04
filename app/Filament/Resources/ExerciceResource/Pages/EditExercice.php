<?php

namespace App\Filament\Resources\ExerciceResource\Pages;

use App\Filament\Resources\ExerciceResource;
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
