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
                'code'        => 'certificat_engagement',
                'nom'         => 'CERTIFICAT D\'ENGAGEMENT',
                'template'    => 'pdf.templates.certificat-engagement',
                'description' => 'Certificat d\'engagement budgétaire',
                'categorie'   => 'Budgétaire',
                'ordre'       => 1,
                'champs_variables' => [
                    'montant'        => ['source' => 'montant_total',        'type' => 'money'],
                    'reference'      => ['source' => 'numero',               'type' => 'text'],
                    'date_signature' => ['source' => 'date_emission',        'type' => 'date', 'format' => 'd/m/Y'],
                    'signataire'     => ['source' => 'validateur.name',      'type' => 'text'],
                    'objet'          => ['source' => 'objet',                'type' => 'text'],
                    'beneficiaire'   => ['source' => 'instance_destinataire', 'type' => 'text'],
                    'exercice'       => ['source' => 'exercice',             'type' => 'text'],
                    'chapitre'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.code', 'type' => 'text'],
                    'article'        => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code', 'type' => 'text'],
                    'paragraphe'     => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.libelle', 'type' => 'text'],
                    'programme'      => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.programme.libelle', 'type' => 'text'],
                    'objectif'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.objectif.libelle', 'type' => 'text', 'default' => ''],
                    'action'         => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.libelle', 'type' => 'text'],
                    'activite'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.libelle', 'type' => 'text'],
                    'tache'          => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.libelle', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'VISA DE L\'ORDONNATEUR', 'position' => 'center', 'largeur' => 100]],
                ],
            ],

            // ===================================
            // 2. BON DE COMMANDE ADMINISTRATIF
            // ===================================
            [
                'code'        => 'bon_commande',
                'nom'         => 'BON DE COMMANDE ADMINISTRATIF',
                'template'    => 'pdf.templates.bon-commande',
                'description' => 'Bon de commande administratif',
                'categorie'   => 'Commercial',
                'ordre'       => 2,
                'champs_variables' => [
                    'service'                  => ['source' => 'service.libelle',          'type' => 'uppercase'],
                    'numero_bca'               => ['source' => 'numero',                   'type' => 'text'],
                    'date_impression'          => ['source' => 'created_at',               'type' => 'date', 'format' => 'd/m/Y'],
                    'prestataire_nom'          => ['source' => 'fournisseur.nom',          'type' => 'text'],
                    'prestataire_adresse'      => ['source' => 'fournisseur.adresse',      'type' => 'text'],
                    'prestataire_tel'          => ['source' => 'fournisseur.telephone',    'type' => 'text'],
                    'prestataire_contribuable' => ['source' => 'fournisseur.numero_contribuable', 'type' => 'text'],
                    'quantite'                 => ['source' => 'quantite',                 'type' => 'number', 'decimals' => 0],
                    'designation'              => ['source' => 'designation',              'type' => 'text'],
                    'objet'                    => ['source' => 'objet',                    'type' => 'text'],
                    'prix_unitaire'            => ['source' => 'prix_unitaire',            'type' => 'number', 'decimals' => 0],
                    'montant'                  => ['source' => 'montant_total',            'type' => 'money'],
                    'montant_ht'               => ['source' => 'montant_ht',               'type' => 'money'],
                    'montant_tva'              => ['source' => 'montant_tva',              'type' => 'money'],
                    'montant_ttc'              => ['source' => 'montant_ttc',              'type' => 'money'],
                    'articles'                 => ['source' => 'lignes',                   'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
            ],

            // ===================================
            // 3. AUTORISATION D'ENGAGEMENT
            // ===================================
            [
                'code'        => 'autorisation_engagement',
                'nom'         => 'AUTORISATION D\'ENGAGEMENT',
                'template'    => 'pdf.templates.autorisation-engagement',
                'description' => 'Autorisation d\'engagement budgétaire',
                'categorie'   => 'Budgétaire',
                'ordre'       => 3,
                'champs_variables' => [
                    'montant'        => ['source' => 'montant_total',        'type' => 'money'],
                    'reference'      => ['source' => 'numero',               'type' => 'text'],
                    'date_signature' => ['source' => 'date_emission',        'type' => 'date', 'format' => 'd/m/Y'],
                    'signataire'     => ['source' => 'validateur.name',      'type' => 'text'],
                    'objet'          => ['source' => 'objet',                'type' => 'text'],
                    'beneficiaire'   => ['source' => 'instance_destinataire', 'type' => 'text'],
                    'exercice'       => ['source' => 'exercice',             'type' => 'text'],
                    'chapitre'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.parent.code', 'type' => 'text'],
                    'article'        => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code', 'type' => 'text'],
                    'paragraphe'     => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.code', 'type' => 'text'],
                    'programme'      => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.programme.libelle', 'type' => 'text'],
                    'objectif'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.objectif.libelle', 'type' => 'text', 'default' => ''],
                    'action'         => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.libelle', 'type' => 'text'],
                    'activite'       => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.libelle', 'type' => 'text'],
                    'tache'          => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.libelle', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher'   => true,
                    'signatures' => [['titre' => 'VISA DE L\'ORDONNATEUR', 'position' => 'center', 'largeur' => 100]],
                ],
            ],

            // ===================================
            // 4. BORDEREAU D'ENGAGEMENT
            // ===================================
            [
                'code'        => 'bordereau_engagement',
                'nom'         => 'BORDEREAU D\'ENGAGEMENT',
                'template'    => 'pdf.templates.bordereau-engagement',
                'description' => 'Bordereau récapitulatif des engagements',
                'categorie'   => 'Budgétaire',
                'ordre'       => 4,
                'champs_variables' => [
                    'numero'  => ['source' => 'numero',          'type' => 'text'],
                    'date'    => ['source' => 'date_emission',   'type' => 'date', 'format' => 'd/m/Y'],
                    'exercice' => ['source' => 'exercice.annee',  'type' => 'text'],
                    'budget'  => ['source' => 'budget.libelle',  'type' => 'text'],
                    'total'   => ['source' => 'montant_total',   'type' => 'money'],
                    'lignes'  => ['source' => 'lignes',          'type' => 'array'],
                ],
                'calculs' => [
                    'total_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher'   => true,
                    'signatures' => [['titre' => 'ORDONNATEUR', 'position' => 'right', 'largeur' => 90]],
                ],
            ],

            // ===================================
            // 5. ✅ MÉMOIRE DE DÉPENSE
            // ===================================
            [
                'code'        => 'memoire_depense',
                'nom'         => 'MÉMOIRE DE DÉPENSE',
                'template'    => 'pdf.memoire-depense',
                'description' => 'Mémoire de dépense avec tableau des lignes HT/TVA/IR/TTC',
                'categorie'   => 'Commercial',
                'ordre'       => 5,
                'orientation' => 'landscape',
                'format_papier' => 'A4',
                'champs_variables' => [
                    'numero'           => ['source' => 'numero',              'type' => 'text'],
                    'exercice'         => ['source' => 'exercice',            'type' => 'text'],
                    'date_memoire'     => ['source' => 'date_memoire',        'type' => 'date', 'format' => 'd/m/Y'],
                    'objet'            => ['source' => 'objet',               'type' => 'text'],
                    'numero_decision'  => ['source' => 'numero_decision',     'type' => 'text', 'default' => ''],
                    'date_decision'    => ['source' => 'date_decision',       'type' => 'date', 'format' => 'd/m/Y', 'default' => ''],
                    'numero_ce'        => ['source' => 'numero_ce',           'type' => 'text', 'default' => ''],
                    'date_ce'          => ['source' => 'date_ce',             'type' => 'date', 'format' => 'd/m/Y', 'default' => ''],
                    'montant_ht'       => ['source' => 'montant_ht',          'type' => 'money'],
                    'montant_tva'      => ['source' => 'montant_tva',         'type' => 'money'],
                    'montant_ir'       => ['source' => 'montant_ir',          'type' => 'money'],
                    'montant_ttc'      => ['source' => 'montant_ttc',         'type' => 'money'],
                    'montant_net'      => ['source' => 'montant_net',         'type' => 'money'],
                    'montant_lettres'  => ['source' => 'montant_lettres',     'type' => 'text'],
                    'signataire_nom'   => ['source' => 'signataire_nom',      'type' => 'text', 'default' => ''],
                    'signataire_fonction' => ['source' => 'signataire_fonction', 'type' => 'text', 'default' => 'LE DIRECTEUR GÉNÉRAL'],
                    'lieu_signature'   => ['source' => 'lieu_signature',      'type' => 'text', 'default' => 'Yaoundé'],
                    'date_signature'   => ['source' => 'date_signature',      'type' => 'date', 'format' => 'd/m/Y'],
                    'lignes'           => ['source' => 'lignes',              'type' => 'array'],
                ],
                'calculs' => [
                    'montant_ttc_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params'   => ['_raw.montant_ttc'],
                    ],
                    'montant_net_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params'   => ['_raw.montant_net'],
                    ],
                ],
                'signature_config' => [
                    'afficher'   => true,
                    'signatures' => [
                        [
                            'titre'    => 'LE DIRECTEUR GÉNÉRAL',
                            'position' => 'right',
                            'largeur'  => 80,
                        ],
                    ],
                ],
                'options_pdf' => [
                    'orientation' => 'landscape',
                    'format'      => 'A4',
                    'paper_size'  => 'a4',
                ],
            ],
        ];

        foreach ($etats as $etat) {
            EtatConfig::updateOrCreate(
                ['code' => $etat['code']],
                $etat
            );
        }

        $this->command->info('✅ ' . count($etats) . ' configurations d\'états créées/mises à jour.');
    }
}
