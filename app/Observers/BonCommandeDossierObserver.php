<?php

namespace App\Observers;

use App\Models\BonCommande;
use App\Services\DossierFournisseurService;

class BonCommandeDossierObserver
{
    /**
     * BC vient d'être engagé → créer dossier + ajouter BC comme pièce
     */
    public function updated(BonCommande $bc): void
    {
        // ✅ Déclencher uniquement quand le statut passe à 'engage'
        if ($bc->wasChanged('statut') && $bc->statut === 'engage') {
            DossierFournisseurService::traiterEngagementBC($bc);
        }
    }

    /**
     * BC supprimé → retirer sa pièce du dossier
     */
    public function deleted(BonCommande $bc): void
    {
        DossierFournisseurService::supprimerPieceParSource(get_class($bc), $bc->id);
    }

    public function forceDeleted(BonCommande $bc): void
    {
        DossierFournisseurService::supprimerPieceParSource(get_class($bc), $bc->id);
    }
}
