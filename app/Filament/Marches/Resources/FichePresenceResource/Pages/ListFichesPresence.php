<?php

namespace App\Filament\Marches\Resources\FichePresenceResource\Pages;

use App\Filament\Marches\Resources\FichePresenceResource;
use App\Models\FichePresence;
use Filament\Resources\Pages\ListRecords;

/**
 * Le registre des séances : toutes les fiches de présence dressées, marché par marché.
 *
 * Pas de bouton « Créer » ici, et ce n'est pas une cache : la fiche constate une
 * réunion tenue pour un marché, donc cela se décide sur le marché (action « Fiche de
 * présence »), où la fenêtre du cycle et la commission réunie sont lisibles.
 */
class ListFichesPresence extends ListRecords
{
    protected static string $resource = FichePresenceResource::class;

    /**
     * Le chiffre du badge du menu se relit ici : ce que le guichet a dressé aujourd'hui
     * et, surtout, ce qui attend encore un émargement ou un visa — une fiche non signée
     * ne fait pas foi.
     */
    public function getSubheading(): ?string
    {
        return sprintf(
            '%s fiche(s) dressée(s) aujourd\'hui · %s en attente d\'émargement ou de visa.',
            FichePresence::etabliesAujourdhui()->count(),
            FichePresence::query()->enAttente()->count(),
        );
    }
}
