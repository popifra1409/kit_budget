<?php
// database/seeders/TypesContratSeeder.php

namespace Database\Seeders;

use App\Models\TypeContrat;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial des types de contrat et de leur seuil (scans du 27/10 :
 * « ≥ 50 millions → Marché, < 50 millions → Lettre de commande »).
 *
 * Additif : le seuil semé n'est jamais réécrit sur une ligne existante, il se corrige
 * à l'écran. Les autres seuils du scan (250 / 500 / 100 millions) ne sont pas semés :
 * la règle qu'ils expriment reste à confirmer.
 */
class TypesContratSeeder extends Seeder
{
    /** Seuil marché / lettre de commande, en monnaie de l'EP. */
    public const SEUIL_MARCHE = 50000000;

    public function run(): void
    {
        $contrats = [
            ['marche', 'Marché', self::SEUIL_MARCHE, 'gte', 1],
            ['lettre_commande', 'Lettre de commande', self::SEUIL_MARCHE, 'lt', 2],
        ];

        foreach ($contrats as [$code, $libelle, $seuil, $operateur, $ordre]) {
            TypeContrat::firstOrCreate(['code' => $code], [
                'libelle'   => $libelle,
                'seuil_min' => $seuil,
                'operateur' => $operateur,
                'ordre'     => $ordre,
                'actif'     => true,
            ]);
        }

        $this->command?->info('✓ Types de contrat : ' . TypeContrat::count() . ' (aucun existant modifié)');
    }
}
