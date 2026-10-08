<?php

namespace Database\Seeders;

use App\Models\TiersRecette;
use Illuminate\Database\Seeder;

/** Débiteurs / payeurs de base. Additif : ne modifie pas un tiers existant. */
class TiersRecettesSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['CAISSE PRINCIPALE', 'caisse'],
            ['ÉTAT — MINFI', 'subvention'],
            ['ÉTAT — MINSANTE', 'subvention'],
            ['SUBVENTIONS DIVERSES', 'subvention'],
            ['DONS ET LEGS', 'dons_legs'],
            ['ASSURANCES (DIVERS)', 'assurance'],
            ['SOCIÉTÉS CONVENTIONNÉES (DIVERS)', 'societe'],
            ['PATIENTS / PARTICULIERS', 'particulier'],
        ];

        foreach ($tiers as [$nom, $categorie]) {
            TiersRecette::firstOrCreate(['nom' => $nom], ['categorie' => $categorie, 'actif' => true]);
        }

        $this->command?->info('✓ Débiteurs / payeurs : ' . TiersRecette::count());
    }
}
