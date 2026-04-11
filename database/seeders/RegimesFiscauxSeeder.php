<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RegimeFiscal;
use App\Models\TauxIr;

class RegimesFiscauxSeeder extends Seeder
{
    public function run(): void
    {
        // Supprimer les anciens régimes
        RegimeFiscal::truncate();
        TauxIr::truncate();

        // 1. Régime Simplifié
        $simplifie = RegimeFiscal::create([
            'code' => 'SIMPLIFIE',
            'libelle' => 'Régime Simplifié',
            'description' => 'Régime simplifié pour petites entreprises avec CA < 50 millions FCFA',
            'ca_min' => 0,
            'ca_max' => 50000000,
            'taux_ir_defaut' => 5.5,
            'type_calcul_ir' => 'pourcentage',
            'actif' => true,
            'ordre' => 1,
        ]);

        TauxIr::create([
            'regime_fiscal_id' => $simplifie->id,
            'taux' => 5.5,
            'actif' => true,
        ]);

        // 2. Régime Réel
        $reel = RegimeFiscal::create([
            'code' => 'REEL',
            'libelle' => 'Régime Réel',
            'description' => 'Régime réel d\'imposition pour CA ≥ 50 millions FCFA',
            'ca_min' => 50000000,
            'ca_max' => null,
            'taux_ir_defaut' => 2.2,
            'type_calcul_ir' => 'pourcentage',
            'actif' => true,
            'ordre' => 2,
        ]);

        TauxIr::create([
            'regime_fiscal_id' => $reel->id,
            'taux' => 2.2,
            'actif' => true,
        ]);

        $this->command->info('✅ Régimes fiscaux créés avec succès !');
    }
}
