<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\LignePrevisionRecette;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule le montant rectifié des lignes de prévisions de recettes :
 *   rectifié = prévision initiale + Σ mouvements de recettes des collectifs adoptés non annulés.
 *
 * Corrige les doubles comptages causés par l'ancien cumul (« montant_rectifie += mouvement »)
 * lors des réapplications de collectifs. Sans effet sur une ligne déjà juste.
 *
 *   php artisan recettes:recalculer              → simulation (aucune écriture)
 *   php artisan recettes:recalculer --appliquer  → correction, avec trace au journal
 */
class RecalculerRecettes extends Command
{
    protected $signature = 'recettes:recalculer {--appliquer : Enregistrer les corrections (sinon simulation)}';

    protected $description = 'Recalcule le montant rectifié des prévisions de recettes (initial + collectifs adoptés)';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        $this->info($appliquer ? 'MODE CORRECTION' : 'MODE SIMULATION — aucune écriture');

        $lignes = LignePrevisionRecette::query()
            ->whereHas('previsionRecette') // prévisions non supprimées uniquement
            ->with('previsionRecette')
            ->orderBy('prevision_recette_id')
            ->orderBy('code_nomenclature')
            ->get();

        $ecarts = $lignes->map(function (LignePrevisionRecette $ligne) {
            $reel = $ligne->getMontantRectifieReel();
            $actuel = (float) $ligne->montant_rectifie;

            return abs($actuel - $reel) >= 0.01
                ? ['ligne' => $ligne, 'actuel' => $actuel, 'reel' => $reel]
                : null;
        })->filter()->values();

        $this->line("Lignes examinées : {$lignes->count()} | à corriger : {$ecarts->count()}");

        if ($ecarts->isEmpty()) {
            $this->info('✅ Toutes les lignes de recettes sont justes.');
            return self::SUCCESS;
        }

        $this->table(
            ['Prévision', 'Ligne', 'Initial', 'Rectifié actuel', 'Rectifié réel', 'Écart'],
            $ecarts->map(fn($e) => [
                $e['ligne']->previsionRecette?->code,
                $e['ligne']->code_nomenclature . ' ' . mb_strimwidth((string) $e['ligne']->libelle_nomenclature, 0, 35, '…'),
                number_format((float) $e['ligne']->montant_prevu_initial, 0, ',', ' '),
                number_format($e['actuel'], 0, ',', ' '),
                number_format($e['reel'], 0, ',', ' '),
                number_format($e['actuel'] - $e['reel'], 0, ',', ' '),
            ])->all()
        );

        if (!$appliquer) {
            $this->warn('Simulation : relancez avec --appliquer pour corriger (après sauvegarde de la base).');
            return self::SUCCESS;
        }

        if (!$this->confirm('Une sauvegarde complète de la base a-t-elle été faite ?', false)) {
            $this->warn('Correction abandonnée.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($ecarts) {
            foreach ($ecarts as $e) {
                /** @var LignePrevisionRecette $ligne */
                $ligne = $e['ligne'];
                $ligne->recalculerRectifie(); // écart, taux et 12 mois recalculés automatiquement

                ActivityLog::logAction($ligne, 'correction_donnees', [
                    'correctif'       => 'recalcul du montant rectifié des recettes',
                    'ligne'           => $ligne->code_nomenclature,
                    'rectifie_avant'  => $e['actuel'],
                    'rectifie_apres'  => $e['reel'],
                    'cause'           => 'double comptage d\'un mouvement de collectif (ancien cumul)',
                ]);
            }
        });

        $this->info("✅ {$ecarts->count()} ligne(s) corrigée(s).");
        return self::SUCCESS;
    }
}
