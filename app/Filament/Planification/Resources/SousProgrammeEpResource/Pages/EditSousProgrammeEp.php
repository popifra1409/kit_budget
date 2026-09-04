<?php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;

use App\Filament\Planification\Resources\SousProgrammeEpResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSousProgrammeEp extends EditRecord
{
    protected static string $resource = SousProgrammeEpResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
