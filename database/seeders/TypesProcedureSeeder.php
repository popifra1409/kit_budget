<?php
// database/seeders/TypesProcedureSeeder.php

namespace Database\Seeders;

use App\Models\TypeProcedure;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial des procédures de passation (scans du 27/10).
 * Additif : une procédure déjà présente, même renommée à l'écran, n'est jamais modifiée.
 */
class TypesProcedureSeeder extends Seeder
{
    public function run(): void
    {
        $procedures = [
            ['ao_ouvert',   "Appel d'offres ouvert",    'appel_offre', 'Tous les soumissionnaires peuvent remettre une offre.'],
            ['ao_restr',    "Appel d'offres restreint", 'appel_offre', 'Seuls les candidats admis à concourir remettent une offre.'],
            ['cotation',    'Demande de cotation',      'cotation',    'Consultation simple de plusieurs fournisseurs.'],
            ['gre_a_gre',   'Gré à gré',                'gre_a_gre',   'Marché négocié avec un prestataire choisi.'],
        ];

        $ordre = 0;
        foreach ($procedures as [$code, $libelle, $famille, $description]) {
            TypeProcedure::firstOrCreate(['code' => $code], [
                'libelle'     => $libelle,
                'famille'     => $famille,
                'description' => $description,
                'ordre'       => ++$ordre,
                'actif'       => true,
            ]);
        }

        $this->command?->info('✓ Types de procédure : ' . TypeProcedure::count() . ' (aucun existant modifié)');
    }
}
