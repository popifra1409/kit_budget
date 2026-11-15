<?php
// app/Filament/Planification/Resources/SousProgrammeEpResource/Pages/CreateSousProgrammeEp.php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;

use App\Filament\Planification\Resources\SousProgrammeEpResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * B1b — l'entrée propre des sous-programmes de l'établissement public. La page est autorisée par
 * `SousProgrammeEpResource::canCreate()` (`create_sous_programme_ep`), la même permission que le
 * bouton d'ajout de l'onglet du plan stratégique.
 */
class CreateSousProgrammeEp extends CreateRecord
{
    protected static string $resource = SousProgrammeEpResource::class;
}
