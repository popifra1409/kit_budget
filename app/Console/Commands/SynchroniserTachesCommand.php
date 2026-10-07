<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\LigneBudgetaire;
use App\Services\Budget\SynchronisationTachesService;
use App\Services\StatistiquesBudgetaires;
use Illuminate\Console\Command;

/**
 * Rattrapage : aligne les sous-tâches sur la dotation ACTUALISÉE des lignes (collectifs et virements
 * déjà appliqués avant la mise en place de la synchronisation automatique).
 *
 *   php artisan budget:synchroniser-taches              → simulation
 *   php artisan budget:synchroniser-taches --appliquer  → correction
 */
class SynchroniserTachesCommand extends Command
{
    protected $signature = 'budget:synchroniser-taches {--budget=} {--appliquer : Enregistrer (sinon simulation)} {--force : Sans confirmation (tâche planifiée)}';

    protected $description = 'Aligne les AE/CP des sous-tâches sur la dotation actualisée des lignes budgétaires';

    public function handle(SynchronisationTachesService $service): int
    {
        $budget = $this->option('budget')
            ? Budget::withoutGlobalScope('exercice')->find($this->option('budget'))
            : (StatistiquesBudgetaires::getVueEnsemble()['budget'] ?? null);

        if (!$budget) {
            $this->error('Budget introuvable.');
            return self::FAILURE;
        }

        $appliquer = (bool) $this->option('appliquer');
        $this->info(($appliquer ? 'CORRECTION' : 'SIMULATION') . " — budget {$budget->code}");

        if ($appliquer && !$this->option('force') && !$this->confirm('Une sauvegarde complète de la base a-t-elle été faite ?', false)) {
            return self::FAILURE;
        }

        $lignes = LigneBudgetaire::withoutGlobalScope('exercice')->where('budget_id', $budget->id)->with(['nomenclature', 'budget'])->get();
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $tableau = [];

        foreach ($lignes as $ligne) {
            $r = $service->synchroniser($ligne, $appliquer, 'rattrapage');
            if (abs($r['ecart']) < 1) continue;

            if (empty($r['repartition'])) {
                $tableau[] = [$r['code'], $f($r['dotation']), $f($r['cp_taches']), $f($r['ecart']), '⚠️ aucune sous-tâche : à programmer'];
            }
            foreach ($r['repartition'] as $p) {
                $tableau[] = [
                    $r['code'],
                    $f($r['dotation']),
                    $f($r['cp_taches']),
                    $f($r['ecart']),
                    "{$p['tache']} : CP {$f($p['cp_avant'])} → {$f($p['cp_apres'])} ; AE {$f($p['ae_avant'])} → {$f($p['ae_apres'])}"
                ];
            }
        }

        if (empty($tableau)) {
            $this->info('✅ Toutes les sous-tâches sont concordantes avec la dotation actualisée des lignes.');
            return self::SUCCESS;
        }

        $this->table(['Compte', 'Dotation act.', 'Σ CP tâches', 'Écart', 'Sous-tâches'], $tableau);
        $this->line($appliquer ? '✅ Sous-tâches mises à jour (tracé au journal d\'audit).' : 'Simulation : relancez avec --appliquer pour corriger.');

        return self::SUCCESS;
    }
}
