<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\LigneBudgetaire;
use App\Models\Tache;
use Illuminate\Support\Collection;

/**
 * Concordance entre la programmation (AE/CP des sous-tâches) et le budget (dotation des lignes).
 * Règle : pour chaque compte, Σ CP des sous-tâches = dotation de la ligne budgétaire.
 * Le rapprochement se fait par CODE de compte (robuste à la reconduction de la nomenclature).
 *
 * Statuts :
 *  - concordant : écart < 1 FCFA avec la dotation ACTUALISÉE (collectifs et virements compris) ;
 *  - ecart      : écart avec la dotation actualisée (rattrapage : php artisan budget:synchroniser-taches) ;
 *  - sans_tache : ligne budgétaire sans aucune sous-tâche (crédits non programmés) ;
 *  - sans_ligne : sous-tâches dont le compte n'a pas de ligne dans ce budget.
 */
class ConcordanceAeCpService
{
    public function analyser(Budget $budget): Collection
    {
        $lignes = LigneBudgetaire::withoutGlobalScope('exercice')
            ->where('budget_id', $budget->id)
            ->with('nomenclature')
            ->get()
            ->keyBy(fn($l) => $l->nomenclature?->code);

        $sousTaches = Tache::withoutGlobalScope('exercice')
            ->where('exercice_id', $budget->exercice_id)
            ->where('niveau', 'sous_tache')
            ->whereNotNull('nomenclature_id')
            ->with('nomenclature')
            ->get()
            ->groupBy(fn($t) => $t->nomenclature?->code);

        $codes = $lignes->keys()->merge($sousTaches->keys())->filter()->unique()->sort()->values();

        return $codes->map(function ($code) use ($lignes, $sousTaches) {
            $ligne = $lignes->get($code);
            $taches = $sousTaches->get($code, collect());

            $initiale = (float) ($ligne?->budget_initial ?? 0);
            $actualisee = $ligne ? (float) $ligne->getBudgetRectifieReel() : 0.0;
            $ae = (float) $taches->sum('ae');
            $cp = (float) $taches->sum('cp');

            $statut = match (true) {
                !$ligne                         => 'sans_ligne',
                $taches->isEmpty()              => 'sans_tache',
                abs($cp - $actualisee) < 1      => 'concordant',
                default                         => 'ecart',
            };

            return [
                'code'              => $code,
                'libelle'           => $ligne?->nomenclature?->libelle ?? $taches->first()?->nomenclature?->libelle,
                'ligne_id'          => $ligne?->id,
                'dotation_initiale' => $initiale,
                'dotation_actualisee' => $actualisee,
                'ae_taches'         => $ae,
                'cp_taches'         => $cp,
                'nb_sous_taches'    => $taches->count(),
                'sous_taches'       => $taches->map(fn($t) => "{$t->code} (CP " . number_format((float) $t->cp, 0, ',', ' ') . ')')->implode(', '),
                'ecart_initiale'    => round($cp - $initiale, 2),
                'ecart_actualisee'  => round($cp - $actualisee, 2),
                'ae_differe_cp'     => abs($ae - $cp) >= 1,
                'statut'            => $statut,
            ];
        });
    }

    public function synthese(Collection $analyse): array
    {
        return [
            'comptes'           => $analyse->count(),
            'concordants'       => $analyse->where('statut', 'concordant')->count(),
            'ecarts'            => $analyse->where('statut', 'ecart')->count(),
            'sans_tache'        => $analyse->where('statut', 'sans_tache')->count(),
            'sans_ligne'        => $analyse->where('statut', 'sans_ligne')->count(),
            'multi_taches'      => $analyse->where('nb_sous_taches', '>', 1)->count(),
            'total_dotation'    => (float) $analyse->sum('dotation_initiale'),
            'total_actualisee'  => (float) $analyse->sum('dotation_actualisee'),
            'total_ae'          => (float) $analyse->sum('ae_taches'),
            'total_cp'          => (float) $analyse->sum('cp_taches'),
        ];
    }
}
