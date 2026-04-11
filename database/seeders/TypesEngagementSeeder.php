<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TypeEngagement;

class TypesEngagementSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'BC',
                'libelle' => 'Bon de Commande Administratif',
                'description' => 'Bon de commande pour montant < 5 millions FCFA',
                'montant_min' => 0,
                'montant_max' => 5000000,
                'mode_calcul_ir' => 'fixe',
                'taux_ir_fixe' => 5.5,
                'taux_ir_regime_reel' => null,
                'taux_ir_regime_simplifie' => null,
                'actif' => true,
                'ordre' => 1,
            ],
            [
                'code' => 'LC',
                'libelle' => 'Lettre-Commande',
                'description' => 'Marché public pour montant entre 5 et 50 millions FCFA',
                'montant_min' => 5000000,
                'montant_max' => 50000000,
                'mode_calcul_ir' => 'selon_regime',
                'taux_ir_fixe' => null,
                'taux_ir_regime_reel' => 2.2,
                'taux_ir_regime_simplifie' => 5.5,
                'actif' => true,
                'ordre' => 2,
            ],
            [
                'code' => 'MARCHE',
                'libelle' => 'Marché Public',
                'description' => 'Marché public pour montant ≥ 50 millions FCFA',
                'montant_min' => 50000000,
                'montant_max' => null,
                'mode_calcul_ir' => 'selon_regime',
                'taux_ir_fixe' => null,
                'taux_ir_regime_reel' => 2.2,
                'taux_ir_regime_simplifie' => 5.5,
                'actif' => true,
                'ordre' => 3,
            ],
            [
                'code' => 'DECOMPTE_LC',
                'libelle' => 'Décompte Lettre-Commande',
                'description' => 'Décompte d\'exécution pour lettre-commande',
                'montant_min' => null,
                'montant_max' => null,
                'mode_calcul_ir' => 'selon_regime',
                'taux_ir_fixe' => null,
                'taux_ir_regime_reel' => 2.2,
                'taux_ir_regime_simplifie' => 5.5,
                'actif' => true,
                'ordre' => 4,
            ],
            [
                'code' => 'DECOMPTE_MARCHE',
                'libelle' => 'Décompte Marché',
                'description' => 'Décompte d\'exécution pour marché',
                'montant_min' => null,
                'montant_max' => null,
                'mode_calcul_ir' => 'selon_regime',
                'taux_ir_fixe' => null,
                'taux_ir_regime_reel' => 2.2,
                'taux_ir_regime_simplifie' => 5.5,
                'actif' => true,
                'ordre' => 5,
            ],
        ];

        foreach ($types as $type) {
            TypeEngagement::create($type);
        }

        $this->command->info('✅ Types d\'engagement créés avec succès !');
    }
}
