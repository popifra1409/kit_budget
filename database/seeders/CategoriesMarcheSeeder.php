<?php
// database/seeders/CategoriesMarcheSeeder.php

namespace Database\Seeders;

use App\Models\CategorieMarche;
use Illuminate\Database\Seeder;

/**
 * Référentiel des catégories de marché demandées pour le paramétrage :
 * pluriannuel, annuel, mensuel.
 *
 * Additif. « duree_mois » est une durée de référence proposée à la création du marché,
 * pas une contrainte : elle se rectifie à l'écran si la lecture du besoin diffère.
 */
class CategoriesMarcheSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['pluriannuel', 'Marché pluriannuel', null, true,  1, 'Exécution sur plusieurs exercices, engagement par tranches.'],
            ['annuel',      'Marché annuel',      12,   false, 2, 'Exécution sur un seul exercice.'],
            ['mensuel',     'Marché mensuel',     1,    false, 3, 'Exécution ou renouvellement mensuel.'],
        ];

        foreach ($categories as [$code, $libelle, $duree, $pluri, $ordre, $description]) {
            CategorieMarche::firstOrCreate(['code' => $code], [
                'libelle'      => $libelle,
                'duree_mois'   => $duree,
                'pluri_annuel' => $pluri,
                'ordre'        => $ordre,
                'description'  => $description,
                'actif'        => true,
            ]);
        }

        $this->command?->info('✓ Catégories de marché : ' . CategorieMarche::count() . ' (aucune existante modifiée)');
    }
}
