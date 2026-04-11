<?php

namespace App\Observers;

use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;

class LignePrevisionRecetteObserver
{
    public function created(LignePrevisionRecette $ligne): void
    {
        // Créer automatiquement les 12 prévisions mensuelles
        try {
            PrevisionRecetteMensuelle::creerPrevisionsAnnuelles($ligne);
        } catch (\Exception $e) {
            \Log::warning('Erreur création mensuelles: ' . $e->getMessage());
        }
    }

    public function updated(LignePrevisionRecette $ligne): void
    {
        // Si le montant rectifié change → redistribuer sur 12 mois
        if ($ligne->isDirty('montant_rectifie') || $ligne->isDirty('montant_prevu_initial')) {
            try {
                PrevisionRecetteMensuelle::redistribuerMontant($ligne, $ligne->montant_rectifie);
            } catch (\Exception $e) {
                \Log::warning('Erreur redistribution mensuelles: ' . $e->getMessage());
            }
        }
    }
}
