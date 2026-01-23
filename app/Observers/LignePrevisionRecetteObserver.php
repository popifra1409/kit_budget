<?php

namespace App\Observers;

use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;

class LignePrevisionRecetteObserver
{
    /**
     * Handle the LignePrevisionRecette "created" event.
     */
    public function created(LignePrevisionRecette $ligne): void
    {
        // Crée automatiquement les 12 prévisions mensuelles
        PrevisionRecetteMensuelle::creerPrevisionsAnnuelles($ligne);
    }
}
