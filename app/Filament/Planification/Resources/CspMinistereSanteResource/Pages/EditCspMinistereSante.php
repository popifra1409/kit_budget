<?php

namespace App\Filament\Planification\Resources\CspMinistereSanteResource\Pages;

use App\Filament\Planification\Resources\CspMinistereSanteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCspMinistereSante extends EditRecord
{
    protected static string $resource = CspMinistereSanteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
