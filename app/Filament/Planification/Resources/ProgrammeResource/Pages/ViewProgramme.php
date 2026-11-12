<?php

namespace App\Filament\Planification\Resources\ProgrammeResource\Pages;

use App\Filament\Planification\Resources\ProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Le programme et ses sous-programmes n'avaient que « Modifier » d'affichable : le
 * bouton « Voir » de la table pointait une route jamais déclarée. Cette page ne peut pas
 * se contenter de `ViewRecord` nu — elle hérite de l'`infolist()` du resource, et
 * l'absence de garde d'édition est ce qui la rend utile : un exercice clôturé ou archivé
 * reste consultable alors que son formulaire est refusé.
 */
class ViewProgramme extends ViewRecord
{
    protected static string $resource = ProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        // EditAction se charge lui-même de `canEdit()` ; le rendre visible puis le refuser
        // à l'ouverture serait le piège habituel de ces en-têtes.
        return [
            Actions\EditAction::make(),
        ];
    }
}
