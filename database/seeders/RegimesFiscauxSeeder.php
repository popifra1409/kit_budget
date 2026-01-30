<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RegimeFiscal;
use App\Models\TauxIr;

class RegimesFiscauxSeeder extends Seeder
{
    public function run(): void
    {
        // 1. IGS (Impôt Général Synthétique) - CA < 50 M FCFA
        $igs = RegimeFiscal::create([
            'code' => 'IGS',
            'libelle' => 'Impôt Général Synthétique',
            'description' => 'Régime simplifié pour les petites entreprises avec CA < 50 millions FCFA (Loi de finances 2025-2026)',
            'ca_min' => 0,
            'ca_max' => 50000000, // 50 M FCFA
            'taux_ir_defaut' => 2.2,
            'type_calcul_ir' => 'pourcentage',
            'actif' => true,
            'ordre' => 1,
        ]);

        // Taux IGS
        TauxIr::create([
            'regime_fiscal_id' => $igs->id,
            'taux' => 2.2,
            'actif' => true,
        ]);

        // 2. Régime Réel - CA ≥ 50 M FCFA
        $reel = RegimeFiscal::create([
            'code' => 'REEL',
            'libelle' => 'Régime Réel d\'Imposition',
            'description' => 'Régime standard avec comptabilité complète pour CA ≥ 50 millions FCFA',
            'ca_min' => 50000000, // 50 M FCFA
            'ca_max' => null,
            'taux_ir_defaut' => 5.5,
            'type_calcul_ir' => 'tranche',
            'actif' => true,
            'ordre' => 2,
        ]);

        // Taux Régime Réel par type de prestation
        // IR 5,5% - Prestations de services
        TauxIr::create([
            'regime_fiscal_id' => $reel->id,
            'montant_min' => 0,
            'montant_max' => null,
            'taux' => 5.5,
            'actif' => true,
        ]);

        // IR 1% - Ventes de marchandises
        TauxIr::create([
            'regime_fiscal_id' => $reel->id,
            'montant_min' => 0,
            'montant_max' => null,
            'taux' => 1.0,
            'actif' => false, // Désactivé par défaut, à activer selon le type d'opération
        ]);

        // IR 15% - Marchés de travaux
        TauxIr::create([
            'regime_fiscal_id' => $reel->id,
            'montant_min' => 0,
            'montant_max' => null,
            'taux' => 15.0,
            'actif' => false, // Désactivé par défaut
        ]);

        // 3. Exonéré (pour organisations internationales, etc.)
        $exonere = RegimeFiscal::create([
            'code' => 'EXONERE',
            'libelle' => 'Exonéré',
            'description' => 'Fournisseurs exonérés d\'IR (organisations internationales, administrations publiques, etc.)',
            'ca_min' => null,
            'ca_max' => null,
            'taux_ir_defaut' => 0,
            'type_calcul_ir' => 'pourcentage',
            'actif' => true,
            'ordre' => 3,
        ]);

        TauxIr::create([
            'regime_fiscal_id' => $exonere->id,
            'taux' => 0,
            'actif' => true,
        ]);

        $this->command->info('✅ Régimes fiscaux créés avec succès !');
    }
}
