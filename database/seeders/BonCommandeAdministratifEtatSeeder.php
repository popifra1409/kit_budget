<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class BonCommandeAdministratifEtatSeeder extends Seeder
{
    public function run(): void
    {
        EtatConfig::updateOrCreate(
            ['code' => 'bon_commande'],
            [
                'nom' => 'BON DE COMMANDE ADMINISTRATIF',
                'template' => 'pdf.templates.bon-commande',
                'categorie' => 'Administratif',
                'description' => 'Bon de commande format administratif détaillé',
                'ordre' => 1,
                'actif' => true,
                'champs_variables' => [
                    'numero' => [
                        'source' => 'numero',
                        'type' => 'text',
                    ],
                    'date_emission' => [
                        'source' => 'date_emission',
                        'type' => 'date',
                        'format' => 'd/m/Y',
                    ],
                ],
                'calculs' => [
                    'total_en_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_ttc'],
                    ],
                ],
                'entete_config' => [
                    'afficher_logo' => true,
                    'afficher_republique' => true,
                ],
                'signature_config' => [
                    'ordonnateur' => [
                        'fonction' => 'LE DIRECTEUR GENERAL',
                        'afficher' => true,
                    ],
                ],
                'options_pdf' => [
                    'orientation' => 'portrait',
                    'page-size' => 'A4',
                ],
            ]
        );
    }
}
