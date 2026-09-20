<?php

namespace App\Console\Commands;

use App\Models\LigneBudgetaire;
use App\Models\OrdonnancePaiement;
use Illuminate\Console\Command;

class RecalculerLignesBudgetairesPayees extends Command
{
    protected $signature = 'budget:recalculer-payees';

    protected $description = "Recalcule LigneBudgetaire.paye pour tout l'historique deja marque 'payee', "
        . "car ce champ n'etait jamais mis a jour avant le correctif de marquerPayee().";

    public function handle(): int
    {
        $lignes = LigneBudgetaire::whereNotNull('nomenclature_id')->get();
        $this->info("Traitement de {$lignes->count()} lignes budgétaires...");

        $bar = $this->output->createProgressBar($lignes->count());
        $miseAJour = 0;

        foreach ($lignes as $ligne) {
            $totalPaye = (float) OrdonnancePaiement::whereHas('engagement', function ($q) use ($ligne) {
                $q->where('budget_id', $ligne->budget_id)
                    ->where('nomenclature_principale_id', $ligne->nomenclature_id);
            })
                ->where('statut', 'payee')
                ->sum('montant_net');

            if ((float) $ligne->paye !== $totalPaye) {
                $ligne->updateQuietly(['paye' => $totalPaye]);
                $miseAJour++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✔ Terminé. {$miseAJour} ligne(s) mise(s) à jour sur {$lignes->count()}.");

        return self::SUCCESS;
    }
}
