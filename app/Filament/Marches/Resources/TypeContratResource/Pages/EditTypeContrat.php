<?php

namespace App\Filament\Marches\Resources\TypeContratResource\Pages;

use App\Filament\Marches\Resources\TypeContratResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTypeContrat extends EditRecord
{
    protected static string $resource = TypeContratResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
