<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class BonCommandeSimpleEtatSeeder extends Seeder
{
    public function run(): void
    {
        EtatConfig::updateOrCreate(
            ['code' => 'bon_commande_simple'],
            [
                'nom' => 'BON DE COMMANDE SIMPLE',
                'template' => 'pdf.templates.bon-commande-simple',
                'categorie' => 'Commercial',
                'description' => 'Bon de commande format simplifié pour les fournisseurs',
                'ordre' => 2,
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
                    'service_demandeur' => [
                        'source' => 'serviceDemandeur.nom',
                        'type' => 'text',
                    ],
                    'fournisseur_raison_sociale' => [
                        'source' => 'fournisseur.raison_sociale',
                        'type' => 'text',
                    ],
                    'fournisseur_adresse' => [
                        'source' => 'fournisseur.adresse',
                        'type' => 'text',
                    ],
                    'fournisseur_telephone' => [
                        'source' => 'fournisseur.telephone',
                        'type' => 'text',
                    ],
                    'date_livraison_prevue' => [
                        'source' => 'date_livraison_prevue',
                        'type' => 'date',
                        'format' => 'd/m/Y',
                    ],
                    'objet' => [
                        'source' => 'objet',
                        'type' => 'text',
                    ],
                    'montant_ht' => [
                        'source' => 'montant_ht',
                        'type' => 'montant',
                    ],
                    'montant_tva' => [
                        'source' => 'montant_tva',
                        'type' => 'montant',
                    ],
                    'montant_ttc' => [
                        'source' => 'montant_ttc',
                        'type' => 'montant',
                    ],
                    'montant_ir' => [
                        'source' => 'montant_ir',
                        'type' => 'montant',
                    ],
                    'net_a_percevoir' => [
                        'source' => 'net_a_percevoir',
                        'type' => 'montant',
                    ],
                ],
                'calculs' => [
                    'total_en_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.net_a_percevoir'],
                    ],
                ],
                'entete_config' => [
                    'afficher_logo' => true,
                    'afficher_republique' => true,
                    'afficher_numero_commande' => true,
                ],
                'signature_config' => [
                    'directeur' => [
                        'fonction' => 'LE DIRECTEUR DE L\'HOPITAL',
                        'afficher' => true,
                    ],
                ],
                'options_pdf' => [
                    'orientation' => 'portrait',
                    'page-size' => 'A4',
                    'margin-top' => '10mm',
                    'margin-bottom' => '15mm',
                    'margin-left' => '15mm',
                    'margin-right' => '15mm',
                ],
            ]
        );
    }
}
