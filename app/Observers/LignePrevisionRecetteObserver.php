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
            $ligne->alignerMensuelles();
        } catch (\Exception $e) {
            \Log::warning('Erreur création mensuelles: ' . $e->getMessage());
        }
    }

    public function updated(LignePrevisionRecette $ligne): void
    {
        // Si le montant rectifié change → redistribuer sur 12 mois et recalculer
        // écart / taux / cumulés : sinon le tableau de suivi des recettes reste
        // figé sur l'ancien fractionnement après un collectif.
        if ($ligne->isDirty('montant_rectifie') || $ligne->isDirty('montant_prevu_initial')) {
            try {
                $ligne->alignerMensuelles();
            } catch (\Exception $e) {
                \Log::warning('Erreur redistribution mensuelles: ' . $e->getMessage());
            }
        }
    }

    /**
     * Une ligne retirée laissait ses 12 mois derrière elle : le tableau de bord
     * des recettes continuait d'afficher (et de totaliser) des prévisions fantômes.
     */
    public function deleted(LignePrevisionRecette $ligne): void
    {
        PrevisionRecetteMensuelle::where('ligne_prevision_recette_id', $ligne->id)
            ->get()
            ->each
            ->delete();
    }
}
