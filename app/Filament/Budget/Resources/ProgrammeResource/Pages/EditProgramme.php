<?php

namespace App\Filament\Budget\Resources\ProgrammeResource\Pages;

use App\Filament\Budget\Resources\ProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgramme extends EditRecord
{
    protected static string $resource = ProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
