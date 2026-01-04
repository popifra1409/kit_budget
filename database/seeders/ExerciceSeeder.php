<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercice;
use Carbon\Carbon;

class ExerciceSeeder extends Seeder
{
    public function run(): void
    {
        $exercices = [
            // Exercice 2024 - Clôturé
            [
                'annee' => 2024,
                'libelle' => 'Exercice budgétaire 2024',
                'description' => 'Exercice budgétaire de l\'année 2024 - Clôturé',
                'statut' => 'cloture',
                'date_debut' => Carbon::create(2024, 1, 1),
                'date_fin' => Carbon::create(2024, 12, 31),
                'date_cloture' => Carbon::create(2024, 12, 31, 23, 59),
                'actif' => false,
                'reconduction_effectuee' => false,
                'observations' => 'Exercice clôturé - Données historiques',
            ],

            // Exercice 2025 - Actif
            [
                'annee' => 2025,
                'libelle' => 'Exercice budgétaire 2025',
                'description' => 'Exercice budgétaire de l\'année 2025 - En cours',
                'statut' => 'actif',
                'date_debut' => Carbon::create(2025, 1, 1),
                'date_fin' => Carbon::create(2025, 12, 31),
                'actif' => true,
                'reconduction_effectuee' => true,
                'observations' => 'Exercice actif - Reconduit depuis 2024',
            ],

            // Exercice 2026 - Brouillon
            [
                'annee' => 2026,
                'libelle' => 'Exercice budgétaire 2026',
                'description' => 'Exercice budgétaire de l\'année 2026 - En préparation',
                'statut' => 'brouillon',
                'date_debut' => Carbon::create(2026, 1, 1),
                'date_fin' => Carbon::create(2026, 12, 31),
                'actif' => false,
                'reconduction_effectuee' => false,
                'observations' => 'Exercice en préparation pour l\'année prochaine',
            ],
        ];

        foreach ($exercices as $index => $exerciceData) {
            $exercice = Exercice::create($exerciceData);

            // Lier 2025 à 2024 (exercice source)
            if ($exerciceData['annee'] === 2025) {
                $exercice2024 = Exercice::where('annee', 2024)->first();
                if ($exercice2024) {
                    $exercice->exercice_source_id = $exercice2024->id;
                    $exercice->save();
                }
            }

            $this->command->info("✅ Exercice {$exerciceData['annee']} créé ({$exerciceData['statut']})");
        }

        $this->command->info('');
        $this->command->info('🎯 Exercices créés avec succès :');
        $this->command->info('   - 2024 : Clôturé (données historiques)');
        $this->command->info('   - 2025 : ACTIF (exercice en cours)');
        $this->command->info('   - 2026 : Brouillon (préparation)');
    }
}
    