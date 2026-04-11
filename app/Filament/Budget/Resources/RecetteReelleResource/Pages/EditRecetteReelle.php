<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecetteReelle extends EditRecord
{
    protected static string $resource = RecetteReelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
