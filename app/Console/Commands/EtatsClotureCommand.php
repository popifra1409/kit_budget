<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\Exercice;
use App\Services\Budget\EtatsClotureService;
use App\Services\StatistiquesBudgetaires;
use Illuminate\Console\Command;

/** États et taux de fin de gestion (lecture seule).  php artisan cloture:etats [--exercice=2026] */
class EtatsClotureCommand extends Command
{
    protected $signature = 'cloture:etats {--exercice= : Année (par défaut : exercice actif)}';

    protected $description = 'États (RAR, DENO, reste à payer, arriérés, dette) et taux de fin de gestion';

    public function handle(EtatsClotureService $service): int
    {
        $exercice = $this->option('exercice') ? Exercice::where('annee', $this->option('exercice'))->first() : Exercice::getActif();
        $budget = $exercice ? Budget::withoutGlobalScope('exercice')->where('exercice_id', $exercice->id)->orderByDesc('actif')->orderByDesc('id')->first() : null;

        if (!$exercice || !$budget) {
            $this->error('Exercice ou budget introuvable.');
            return self::FAILURE;
        }

        $r = $service->calculer($exercice, $budget);
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');

        $this->info("États de fin de gestion — exercice {$r['exercice']} — budget {$r['budget']}");
        $this->table(['État', 'Montant', 'Détail'], collect(EtatsClotureService::LIBELLES_ETATS)->map(fn($libelle, $cle) => [
            $libelle,
            $f($r['etats'][$cle]['montant']),
            match ($cle) {
                'reste_a_payer' => $r['etats'][$cle]['nombre'] . ' OP (net ' . $f($r['etats'][$cle]['net']) . ')',
                'arrieres'      => $r['etats'][$cle]['nombre'] . ' OP',
                'dette'         => 'DENO ' . $f($r['etats'][$cle]['deno']) . ' + RAP ' . $f($r['etats'][$cle]['reste_a_payer']) . ' + sans service fait ' . $f($r['etats'][$cle]['engage_sans_service_fait']),
                'rar'           => $r['etats'][$cle]['note'] ?? $r['etats'][$cle]['nombre'] . ' recette(s)',
                default         => '',
            },
        ])->values()->all());

        $this->table(['Taux', 'Valeur', 'Numérateur', 'Base'], collect(EtatsClotureService::LIBELLES_TAUX)->map(fn($libelle, $cle) => [
            $libelle,
            $r['taux'][$cle]['valeur'] === null ? '— (base nulle)' : $r['taux'][$cle]['valeur'] . ' %',
            $f($r['taux'][$cle]['numerateur']),
            $f($r['taux'][$cle]['base']) . ' (' . $r['taux'][$cle]['libelle_base'] . ')',
        ])->values()->all());

        $this->line('Liquidé = liquidations arrêtées (' . $f($r['detail_liquidation']['explicite']) . ') + OP émises sans liquidation, service fait implicite ('
            . $f($r['detail_liquidation']['implicite_op_sans_liquidation']) . ').');

        return self::SUCCESS;
    }
}
