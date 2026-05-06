<?php

namespace App\Observers;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;

class EngagementObserver
{
    public function creating(Engagement $engagement): void
    {
        // Ne pas écraser si déjà rempli
        if ($engagement->snapshot_disponible_avant !== null) return;

        $lb = LigneBudgetaire::where('budget_id', $engagement->budget_id)
            ->where('nomenclature_id', $engagement->nomenclature_principale_id)
            ->first();

        if (!$lb) return;

        $budgetRectifie = (float) ($lb->budget_rectifie ?? $lb->budget_initial);
        $montant        = (float) ($engagement->montant_engage ?? 0);

        // Total engagé AVANT cet engagement (tous les engagements existants)
        $totalEngageAvant = Engagement::withoutGlobalScope('exercice')
            ->where('budget_id', $engagement->budget_id)
            ->where('nomenclature_principale_id', $engagement->nomenclature_principale_id)
            ->whereIn('statut', ['provisoire', 'definitif'])
            ->sum('montant_engage');

        $disponibleAvant = $budgetRectifie - $totalEngageAvant;

        $engagement->snapshot_budget_initial    = $lb->budget_initial;
        $engagement->snapshot_budget_rectifie   = $budgetRectifie;
        $engagement->snapshot_total_engage_avant = $totalEngageAvant;
        $engagement->snapshot_disponible_avant   = $disponibleAvant;
        $engagement->snapshot_disponible_apres   = $disponibleAvant - $montant;
    }
}
