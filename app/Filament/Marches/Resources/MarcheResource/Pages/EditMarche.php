<?php

namespace App\Filament\Marches\Resources\MarcheResource\Pages;

use App\Filament\Marches\Resources\MarcheResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * La fiche n'est modifiable qu'en brouillon, ou avec force_update_marche : c'est
 * MarcheResource::canEdit() qui garde la page, et le DeleteAction de l'en-tête
 * hérite de la même règle par canDelete().
 */
class EditMarche extends EditRecord
{
    use GereLeClassementDuMarche;

    protected static string $resource = MarcheResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normaliserClassement($data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
