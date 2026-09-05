<?php

namespace App\Filament\Planification\Resources\ActionSousProgrammeResource\Pages;

use App\Filament\Planification\Resources\ActionSousProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActionSousProgramme extends EditRecord
{
    protected static string $resource = ActionSousProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
