<?php

namespace App\Observers;

use App\Models\LigneBudgetaire;
use App\Services\Budget\SynchronisationTachesService;
use Illuminate\Support\Facades\Log;

/**
 * Concordance permanente : quand la DOTATION d'une ligne change (collectif adopté, virement exécuté,
 * contre-passé ou annulé), les sous-tâches du compte sont ajustées automatiquement.
 * La dotation initiale n'est pas surveillée : en élaboration, ce sont les sous-tâches qui la font.
 * Ne bloque jamais l'opération budgétaire : en cas d'erreur, avertissement au journal.
 */
class LigneBudgetaireConcordanceObserver
{
    protected const CHAMPS_DOTATION = ['budget_rectifie', 'virements_entrants', 'virements_sortants'];

    public function updated(LigneBudgetaire $ligne): void
    {
        if (SynchronisationTachesService::$enCours || !$ligne->wasChanged(self::CHAMPS_DOTATION)) {
            return;
        }

        try {
            app(SynchronisationTachesService::class)->synchroniser($ligne);
        } catch (\Throwable $e) {
            Log::warning('Concordance AE/CP : synchronisation des sous-tâches impossible', [
                'ligne' => $ligne->id,
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
