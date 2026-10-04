<?php
// database/seeders/NaturesServiceFaitSeeder.php

namespace Database\Seeders;

use App\Models\NatureServiceFait;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial des natures de service fait et des preuves attendues.
 * Additif : ne modifie pas une nature ou une preuve déjà présente (modifiable ensuite à l'écran).
 */
class NaturesServiceFaitSeeder extends Seeder
{
    public function run(): void
    {
        $natures = [
            'medicaments_fournitures' => ['Médicaments et fournitures', ['Bon de livraison' => true, 'Procès-verbal de réception conforme' => true, 'Facture' => true]],
            'travaux'                 => ['Travaux', ["Procès-verbal de réception ou état d'avancement validé" => true, 'Décompte' => true, 'Attachement / métré' => false]],
            'prestations'             => ['Prestations de services', ['Attestation de réalisation de la prestation' => true, 'Facture' => true, 'Rapport ou livrable' => false]],
            'equipements'             => ['Équipements', ['Procès-verbal de réception' => true, 'Bon de livraison' => true, 'Facture' => true]],
            'personnel'               => ['Personnel', ["Attestation d'exécution du service" => true, 'État nominatif' => false]],
            'subventions'             => ['Subventions conditionnelles', ['Justificatif de réalisation des conditions prévues' => true]],
        ];

        $ordre = 0;
        foreach ($natures as $code => [$libelle, $preuves]) {
            $nature = NatureServiceFait::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'ordre' => ++$ordre, 'actif' => true]);

            $rang = 0;
            foreach ($preuves as $preuve => $obligatoire) {
                $nature->preuves()->firstOrCreate(['libelle' => $preuve], ['obligatoire' => $obligatoire, 'ordre' => ++$rang]);
            }
        }

        $this->command?->info('✓ Natures de service fait : ' . NatureServiceFait::count() . ' (aucune existante modifiée)');
    }
}
