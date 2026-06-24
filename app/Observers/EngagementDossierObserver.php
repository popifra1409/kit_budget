<?php

namespace App\Observers;

use App\Models\Engagement;
use App\Services\DossierFournisseurService;

class EngagementDossierObserver
{
    /**
     * Engagement créé → ajouter CE au dossier correspondant
     */
    public function created(Engagement $engagement): void
    {
        $engageable = $engagement->engageable;
        if (!$engageable) return;

        if ($engageable instanceof \App\Models\BonCommande) {
            // BC : le dossier a été créé par BonCommandeDossierObserver
            // On ajoute juste le CE
            DossierFournisseurService::ajouterCEAuDossierBC($engageable, $engagement);
        }

        if ($engageable instanceof \App\Models\DecisionAdministrative) {
            // DA : créer le dossier + ajouter DA + CE
            DossierFournisseurService::traiterEngagementDA($engageable, $engagement);
        }
    }

    /**
     * Engagement supprimé → retirer le CE du dossier
     */
    public function deleted(Engagement $engagement): void
    {
        \App\Services\DossierFournisseurService::supprimerPieceParSource(
            get_class($engagement),
            $engagement->id
        );
    }

    public function forceDeleted(Engagement $engagement): void
    {
        \App\Services\DossierFournisseurService::supprimerPieceParSource(
            get_class($engagement),
            $engagement->id
        );
    }
}
