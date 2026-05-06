<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;

class FixSnapshotEngagements extends Command
{
    protected $signature   = 'engagements:fix-snapshots';
    protected $description = 'Recalcule les snapshots budgétaires pour tous les engagements';

    public function handle(): void
    {
        $lignes = LigneBudgetaire::all();
        $total  = 0;

        foreach ($lignes as $lb) {
            $engagements = Engagement::withoutGlobalScope('exercice')
                ->where('budget_id', $lb->budget_id)
                ->where('nomenclature_principale_id', $lb->nomenclature_id)
                ->whereIn('statut', ['provisoire', 'definitif'])
                ->orderBy('date_engagement')
                ->orderBy('id')
                ->get();

            if ($engagements->isEmpty()) continue;

            $budgetRectifie  = (float) ($lb->budget_rectifie ?? $lb->budget_initial);
            $cumul           = 0;

            foreach ($engagements as $engagement) {
                $montant         = (float) $engagement->montant_engage;
                $disponibleAvant = $budgetRectifie - $cumul;

                $engagement->updateQuietly([
                    'snapshot_budget_initial'     => $lb->budget_initial,
                    'snapshot_budget_rectifie'    => $budgetRectifie,
                    'snapshot_total_engage_avant' => $cumul,
                    'snapshot_disponible_avant'   => $disponibleAvant,
                    'snapshot_disponible_apres'   => $disponibleAvant - $montant,
                ]);

                $cumul += $montant;
                $total++;
            }
        }

        $this->info("✅ {$total} engagements mis à jour.");
    }
}
