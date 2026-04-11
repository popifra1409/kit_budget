<?php
// app/Console/Commands/GenererPrevisionsMensuelles.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenererPrevisionsMensuelles extends Command
{
    protected $signature   = 'previsions:generer-mensuelles {prevision_id}';
    protected $description = 'Générer les prévisions mensuelles pour une prévision de recettes';

    public function handle(): void
    {
        $previsionId = $this->argument('prevision_id');

        // ✅ Tout en SQL brut — aucun Eloquent, aucun cast
        $prevision = \DB::table('previsions_recettes')
            ->where('id', $previsionId)
            ->select('id', 'exercice_id')
            ->first();

        if (!$prevision) {
            $this->error("Prévision {$previsionId} introuvable");
            return;
        }

        $exercice = \DB::table('exercices')
            ->where('id', $prevision->exercice_id)
            ->select('id', 'annee')
            ->first();

        if (!$exercice) {
            $this->error("Exercice introuvable");
            return;
        }

        $lignes = \DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $previsionId)
            ->selectRaw('id, CAST(montant_rectifie AS FLOAT) as montant')
            ->get();

        $now      = now()->toDateTimeString();
        $values   = [];
        $bindings = [];

        foreach ($lignes as $ligne) {
            $mensuel = round((float)$ligne->montant / 12, 2);

            for ($mois = 1; $mois <= 12; $mois++) {
                $values[]   = "(?,?,?,?,?,0,?,0,0,0,0,true,?,?)";
                $bindings[] = $ligne->id;
                $bindings[] = $exercice->id;
                $bindings[] = $mois;
                $bindings[] = $exercice->annee;
                $bindings[] = $mensuel;
                $bindings[] = -$mensuel;
                $bindings[] = $now;
                $bindings[] = $now;
            }
        }

        // ✅ INSERT par batches de 100
        foreach (array_chunk($values, 100) as $i => $batch) {
            $batchBindings = array_slice($bindings, $i * 100 * 8, count($batch) * 8);

            \DB::statement("
                INSERT INTO previsions_recettes_mensuelles
                    (ligne_prevision_recette_id, exercice_id, mois, annee,
                     montant_prevu, montant_recouvre, ecart,
                     taux_realisation, montant_cumule_prevu,
                     montant_cumule_recouvre, taux_realisation_cumule,
                     actif, created_at, updated_at)
                VALUES " . implode(',', $batch) . "
                ON CONFLICT ON CONSTRAINT unique_ligne_mois
                DO UPDATE SET
                    montant_prevu = EXCLUDED.montant_prevu,
                    exercice_id   = EXCLUDED.exercice_id,
                    updated_at    = EXCLUDED.updated_at
            ", $batchBindings);

            $this->output->write('.');
        }

        $total = count($lignes) * 12;
        $this->info("\n✅ {$total} prévisions générées");
    }
}
