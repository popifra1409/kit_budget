<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class EtatConfigSeeder extends Seeder
{
    public function run(): void
    {
        $etats = [
            // ===================================
            // 1. CERTIFICAT D'ENGAGEMENT
            // ===================================
            [
                'code' => 'certificat_engagement',
                'nom' => 'CERTIFICAT D\'ENGAGEMENT',
                'template' => 'pdf.templates.certificat-engagement',
                'description' => 'Certificat d\'engagement budgétaire',
                'categorie' => 'Budgétaire',
                'ordre' => 1,
                'champs_variables' => [
                    'montant' => [
                        'source' => 'montant_total',
                        'type' => 'money',
                    ],
                    'reference' => [
                        'source' => 'numero',
                        'type' => 'text',
                    ],
                    'date_signature' => [
                        'source' => 'date_emission',
                        'type' => 'date',
                        'format' => 'd/m/Y',
                    ],
                    'signataire' => [
                        'source' => 'validateur.name',
                        'type' => 'text',
                    ],
                    'objet' => [
                        'source' => 'objet',
                        'type' => 'text',
                    ],
                    'beneficiaire' => [
                        'source' => 'instance_destinataire',
                        'type' => 'text',
                    ],
                    'exercice' => [
                        'source' => 'exercice',
                        'type' => 'text',
                    ],
                    'chapitre' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.code',
                        'type' => 'text',
                    ],
                    'article' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code',
                        'type' => 'text',
                    ],
                    'paragraphe' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.libelle',
                        'type' => 'text',
                    ],
                    'programme' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.programme.libelle',
                        'type' => 'text',
                    ],
                    'objectif' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.objectif.libelle',
                        'type' => 'text',
                        'default' => '',
                    ],
                    'action' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.libelle',
                        'type' => 'text',
                    ],
                    'activite' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.libelle',
                        'type' => 'text',
                    ],
                    'tache' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.libelle',
                        'type' => 'text',
                    ],
                ],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_total'],
                    ],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        [
                            'titre' => 'VISA DE L\'ORDONNATEUR',
                            'position' => 'center',
                            'largeur' => 100,
                        ],
                    ],
                ],
            ],

            // ===================================
            // 2. BON DE COMMANDE ADMINISTRATIF
            // ===================================
            [
                'code' => 'bon_commande',
                'nom' => 'BON DE COMMANDE ADMINISTRATIF',
                'template' => 'pdf.templates.bon-commande',
                'description' => 'Bon de commande administratif',
                'categorie' => 'Commercial',
                'ordre' => 2,
                'champs_variables' => [
                    'service' => [
                        'source' => 'service.libelle',
                        'type' => 'uppercase',
                    ],
                    'numero_bca' => [
                        'source' => 'numero',
                        'type' => 'text',
                    ],
                    'date_impression' => [
                        'source' => 'created_at',
                        'type' => 'date',
                        'format' => 'd/m/Y',
                    ],
                    'prestataire_nom' => [
                        'source' => 'fournisseur.nom',
                        'type' => 'text',
                    ],
                    'prestataire_adresse' => [
                        'source' => 'fournisseur.adresse',
                        'type' => 'text',
                    ],
                    'prestataire_tel' => [
                        'source' => 'fournisseur.telephone',
                        'type' => 'text',
                    ],
                    'prestataire_contribuable' => [
                        'source' => 'fournisseur.numero_contribuable',
                        'type' => 'text',
                    ],
                    'quantite' => [
                        'source' => 'quantite',
                        'type' => 'number',
                        'decimals' => 0,
                    ],
                    'designation' => [
                        'source' => 'designation',
                        'type' => 'text',
                    ],
                    'objet' => [
                        'source' => 'objet',
                        'type' => 'text',
                    ],
                    'prix_unitaire' => [
                        'source' => 'prix_unitaire',
                        'type' => 'number',
                        'decimals' => 0,
                    ],
                    'montant' => [
                        'source' => 'montant_total',
                        'type' => 'money',
                    ],
                    'montant_ht' => [
                        'source' => 'montant_ht',
                        'type' => 'money',
                    ],
                    'montant_tva' => [
                        'source' => 'montant_tva',
                        'type' => 'money',
                    ],
                    'montant_ttc' => [
                        'source' => 'montant_ttc',
                        'type' => 'money',
                    ],
                    'articles' => [
                        'source' => 'lignes',
                        'type' => 'array',
                    ],
                ],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_ttc'],
                    ],
                ],
                'signature_config' => [
                    'afficher' => false,
                ],
            ],

            // ===================================
            // 3. AUTORISATION D'ENGAGEMENT
            // ===================================
            [  // ← CORRECTION : Ajouté à l'intérieur du tableau $etats
                'code' => 'autorisation_engagement',
                'nom' => 'AUTORISATION D\'ENGAGEMENT',
                'template' => 'pdf.templates.autorisation-engagement',
                'description' => 'Autorisation d\'engagement budgétaire',
                'categorie' => 'Budgétaire',
                'ordre' => 3,
                'champs_variables' => [
                    'montant' => [
                        'source' => 'montant_total',
                        'type' => 'money',
                    ],
                    'reference' => [
                        'source' => 'numero',
                        'type' => 'text',
                    ],
                    'date_signature' => [
                        'source' => 'date_emission',
                        'type' => 'date',
                        'format' => 'd/m/Y',
                    ],
                    'signataire' => [
                        'source' => 'validateur.name',
                        'type' => 'text',
                    ],
                    'objet' => [
                        'source' => 'objet',
                        'type' => 'text',
                    ],
                    'beneficiaire' => [
                        'source' => 'instance_destinataire',
                        'type' => 'text',
                    ],
                    'exercice' => [
                        'source' => 'exercice',
                        'type' => 'text',
                    ],
                    'chapitre' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.parent.code',
                        'type' => 'text',
                    ],
                    'article' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code',
                        'type' => 'text',
                    ],
                    'paragraphe' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.code',
                        'type' => 'text',
                    ],
                    'programme' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.programme.libelle',
                        'type' => 'text',
                    ],
                    'objectif' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.objectif.libelle',
                        'type' => 'text',
                        'default' => '',
                    ],
                    'action' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.libelle',
                        'type' => 'text',
                    ],
                    'activite' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.libelle',
                        'type' => 'text',
                    ],
                    'tache' => [
                        'source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.libelle',
                        'type' => 'text',
                    ],
                ],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_total'],
                    ],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        [
                            'titre' => 'VISA DE L\'ORDONNATEUR',
                            'position' => 'center',
                            'largeur' => 100,
                        ],
                    ],
                ],
            ],

        ]; // ← FIN du tableau $etats

        // Créer ou mettre à jour les états
        foreach ($etats as $etat) {
            EtatConfig::updateOrCreate(
                ['code' => $etat['code']],
                $etat
            );
        }

        $this->command->info('✅ ' . count($etats) . ' configurations d\'états créées/mises à jour avec succès.');
    }
}
