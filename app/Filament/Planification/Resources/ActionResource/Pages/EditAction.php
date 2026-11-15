<?php

namespace App\Filament\Planification\Resources\ActionResource\Pages;

use App\Filament\Planification\Resources\ActionResource;
use App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAction extends EditRecord
{
    protected static string $resource = ActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Changé de sous-programme, changée de classification : `programme_id` suit le rattachement,
     * comme à la création — sinon les deux colonnes se contredisent et `Action::saving()` refuse
     * la ligne, sous un message qui n'a rien à voir avec le menu qu'il a fallu rouvrir.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CreationActionSousProgramme::ranger($data);
    }
}
