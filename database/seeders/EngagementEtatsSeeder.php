<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class EngagementEtatsSeeder extends Seeder
{
    public function run(): void
    {
        // Certificat d'engagement
        EtatConfig::updateOrCreate(
            ['code' => 'certificat_engagement'],
            [
                'nom' => 'CERTIFICAT D\'ENGAGEMENT',
                'template' => 'pdf.templates.certificat-engagement',
                'categorie' => 'Budgétaire',
                'description' => 'Certificat d\'engagement budgétaire',
                'ordre' => 10,
                'actif' => true,
                'champs_variables' => [],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_engage'],
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

        // Autorisation d'engagement
        EtatConfig::updateOrCreate(
            ['code' => 'autorisation_engagement'],
            [
                'nom' => 'AUTORISATION D\'ENGAGEMENT',
                'template' => 'pdf.templates.autorisation-engagement',
                'categorie' => 'Budgétaire',
                'description' => 'Autorisation d\'engagement budgétaire',
                'ordre' => 11,
                'actif' => true,
                'champs_variables' => [],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_engage'],
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

        // Fiche de performance
        EtatConfig::updateOrCreate(
            ['code' => 'fiche_performance'],
            [
                'nom' => 'FICHE DE PERFORMANCE',
                'template' => 'pdf.templates.fiche-performance',
                'categorie' => 'Budgétaire',
                'description' => 'Fiche de performance budgétaire',
                'ordre' => 12,
                'actif' => true,
                'champs_variables' => [],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_engage'],
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
