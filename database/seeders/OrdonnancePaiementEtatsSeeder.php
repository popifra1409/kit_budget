<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class OrdonnancePaiementEtatsSeeder extends Seeder
{
    public function run(): void
    {
        // Ordonnance de Paiement Standard
        EtatConfig::updateOrCreate(
            ['code' => 'ordonnance_paiement'],
            [
                'nom' => 'ORDONNANCE DE PAIEMENT',
                'template' => 'pdf.templates.ordonnance-paiement',
                'categorie' => 'Budgétaire',
                'description' => 'Ordonnance de paiement standard',
                'ordre' => 20,
                'actif' => true,
                'champs_variables' => [],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_net'],
                    ],
                ],
                'entete_config' => [
                    'afficher_logo' => true,
                ],
                'options_pdf' => [
                    'orientation' => 'portrait',
                    'page-size' => 'A4',
                ],
            ]
        );

        // Ordonnance de Paiement Impôt
        EtatConfig::updateOrCreate(
            ['code' => 'ordonnance_paiement_impot'],
            [
                'nom' => 'ORDONNANCE DE PAIEMENT - IMPÔT',
                'template' => 'pdf.templates.ordonnance-paiement-impot',
                'categorie' => 'Budgétaire',
                'description' => 'Ordonnance de paiement pour impôts et taxes',
                'ordre' => 21,
                'actif' => true,
                'champs_variables' => [],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_impot'],
                    ],
                ],
                'entete_config' => [
                    'afficher_logo' => true,
                ],
                'options_pdf' => [
                    'orientation' => 'portrait',
                    'page-size' => 'A4',
                ],
            ]
        );
    }
}
