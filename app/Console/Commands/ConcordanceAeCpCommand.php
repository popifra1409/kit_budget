<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Services\Budget\ConcordanceAeCpService;
use App\Services\StatistiquesBudgetaires;
use Illuminate\Console\Command;

/**
 * Diagnostic de concordance AE/CP des sous-tâches ↔ dotations des lignes budgétaires. Lecture seule.
 *
 *   php artisan budget:concordance              → budget actif, synthèse + comptes en écart
 *   php artisan budget:concordance --tous       → tous les comptes, y compris concordants
 *   php artisan budget:concordance --budget=3   → un autre budget
 */
class ConcordanceAeCpCommand extends Command
{
    protected $signature = 'budget:concordance {--budget= : Identifiant du budget (par défaut : budget actif)} {--tous : Afficher aussi les comptes concordants}';

    protected $description = 'Concordance entre les AE/CP des sous-tâches et les dotations des lignes budgétaires';

    public function handle(ConcordanceAeCpService $service): int
    {
        $budget = $this->option('budget')
            ? Budget::withoutGlobalScope('exercice')->find($this->option('budget'))
            : (StatistiquesBudgetaires::getVueEnsemble()['budget'] ?? null);

        if (!$budget) {
            $this->error('Budget introuvable.');
            return self::FAILURE;
        }

        $analyse = $service->analyser($budget);
        $s = $service->synthese($analyse);
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');

        $this->info("Budget {$budget->code} — {$budget->libelle} (exercice {$budget->exercice})");
        $this->table(['Indicateur', 'Valeur'], [
            ['Comptes examinés', $s['comptes']],
            ['✅ Concordants (Σ CP sous-tâches = dotation actualisée)', $s['concordants']],
            ['❌ En écart', $s['ecarts']],
            ['⚠️ Lignes sans sous-tâche (crédits non programmés)', $s['sans_tache']],
            ['⚠️ Sous-tâches sans ligne budgétaire', $s['sans_ligne']],
            ['Comptes portés par plusieurs sous-tâches', $s['multi_taches']],
            ['Total dotations initiales', $f($s['total_dotation'])],
            ['Total dotations actualisées', $f($s['total_actualisee'])],
            ['Total AE des sous-tâches', $f($s['total_ae'])],
            ['Total CP des sous-tâches', $f($s['total_cp'])],
            ['Écart global CP − dotation actualisée', $f($s['total_cp'] - $s['total_actualisee'])],
        ]);

        $lignes = $this->option('tous') ? $analyse : $analyse->where('statut', '!=', 'concordant');

        $this->table(
            ['Compte', 'Statut', 'Dotation init.', 'Dotation act.', 'Σ CP tâches', 'Σ AE tâches', 'Écart (act.)', 'Nb ST'],
            $lignes->map(fn($l) => [
                $l['code'],
                $l['statut'],
                $f($l['dotation_initiale']),
                $f($l['dotation_actualisee']),
                $f($l['cp_taches']),
                $f($l['ae_taches']) . ($l['ae_differe_cp'] ? ' *' : ''),
                $f($l['ecart_actualisee']),
                $l['nb_sous_taches'],
            ])->values()->all()
        );

        $this->line('* AE ≠ CP sur ce compte (normal pour une opération pluriannuelle).');

        return self::SUCCESS;
    }
}
