<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtatConfig;

class EtatConfigSeeder extends Seeder
{
    public function run(): void
    {
        $etats = [
            // ═══════════════════════════════════════════════════
            // FICHE DE PERFORMANCE — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'fiche_performance',
                'type_document' => 'fiche_performance',
                'est_defaut' => true,
                'nom' => 'Fiche de Performance',
                'template' => 'pdf.templates.fiche-performance',
                'description' => 'Fiche de suivi de performance des engagements',
                'categorie' => 'Budgétaire',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'exercice' => ['source' => 'exercice.annee', 'type' => 'text'],
                    'programme' => ['source' => 'nomenclaturePrincipale.tache.activite.action.programme.libelle', 'type' => 'text'],
                    'action' => ['source' => 'nomenclaturePrincipale.tache.activite.action.libelle', 'type' => 'text'],
                    'activite' => ['source' => 'nomenclaturePrincipale.tache.activite.libelle', 'type' => 'text'],
                    'tache' => ['source' => 'nomenclaturePrincipale.tache.libelle', 'type' => 'text'],
                    'indicateur' => ['source' => 'nomenclaturePrincipale.tache.indicateur_resultat', 'type' => 'text'],
                    'niveau_avancement' => ['source' => 'nomenclaturePrincipale.tache.niveau_avancement', 'type' => 'text'],
                    'montant' => ['source' => 'montant_engage', 'type' => 'money'],
                    'date_engagement' => ['source' => 'date_engagement', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'imputation' => ['source' => 'nomenclaturePrincipale.code', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => [
                        'fonction' => 'nombre_en_lettres',
                        'params' => ['_raw.montant_engage'],
                    ],
                ],
                'signature_config' => ['afficher' => false],
                'options_pdf' => ['orientation' => 'portrait', 'format' => 'A4'],
            ],

            // ═══════════════════════════════════════════════════
            // CERTIFICAT D'ENGAGEMENT — 2 variantes
            // ═══════════════════════════════════════════════════
            [
                'code' => 'ce_standard',
                'type_document' => 'certificat_engagement',
                'est_defaut' => true,
                'nom' => 'CE Standard',
                'template' => 'pdf.templates.certificat-engagement',
                'description' => 'Certificat d\'engagement standard',
                'categorie' => 'Budgétaire',
                'ordre' => 1,
                'champs_variables' => [
                    'montant' => ['source' => 'montant_total', 'type' => 'money'],
                    'reference' => ['source' => 'numero', 'type' => 'text'],
                    'date_signature' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'signataire' => ['source' => 'validateur.name', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire' => ['source' => 'instance_destinataire', 'type' => 'text'],
                    'exercice' => ['source' => 'exercice', 'type' => 'text'],
                    'chapitre' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.code', 'type' => 'text'],
                    'article' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code', 'type' => 'text'],
                    'paragraphe' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.libelle', 'type' => 'text'],
                    'programme' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.programme.libelle', 'type' => 'text'],
                    'action' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.action.libelle', 'type' => 'text'],
                    'activite' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.activite.libelle', 'type' => 'text'],
                    'tache' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.tache.libelle', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'VISA DE L\'ORDONNATEUR', 'position' => 'center', 'largeur' => 100]],
                ],
            ],
            [
                'code' => 'ce_preimprime',
                'type_document' => 'certificat_engagement',
                'est_defaut' => false,
                'nom' => 'CE Préimprimé (sans en-tête)',
                'template' => 'pdf.templates.certificat-engagement-preimprime',
                'description' => 'CE pour formulaire préimprimé — sans en-tête ni footer',
                'categorie' => 'Budgétaire',
                'ordre' => 2,
                'champs_variables' => [
                    'montant' => ['source' => 'montant_total', 'type' => 'money'],
                    'reference' => ['source' => 'numero', 'type' => 'text'],
                    'date_signature' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire' => ['source' => 'instance_destinataire', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => ['afficher' => false],
            ],

            // ═══════════════════════════════════════════════════
            // BON DE COMMANDE — 3 variantes
            // ═══════════════════════════════════════════════════
            [
                'code' => 'bon_commande',
                'type_document' => 'bon_commande',
                'est_defaut' => true,
                'nom' => 'BC Administratif Standard',
                'template' => 'pdf.templates.bon-commande',
                'description' => 'Bon de commande administratif avec en-tête',
                'categorie' => 'Commercial',
                'ordre' => 1,
                'champs_variables' => [
                    'service' => ['source' => 'service.libelle', 'type' => 'uppercase'],
                    'numero_bca' => ['source' => 'numero', 'type' => 'text'],
                    'date_impression' => ['source' => 'created_at', 'type' => 'date', 'format' => 'd/m/Y'],
                    'prestataire_nom' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'prestataire_adresse' => ['source' => 'fournisseur.adresse', 'type' => 'text'],
                    'prestataire_tel' => ['source' => 'fournisseur.telephone', 'type' => 'text'],
                    'prestataire_contribuable' => ['source' => 'fournisseur.numero_contribuable', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ht' => ['source' => 'montant_ht', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'articles' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
                'entete_config' => [],
            ],
            [
                'code' => 'bon_commande_complet',
                'type_document' => 'bon_commande',
                'est_defaut' => false,
                'nom' => 'BC Complet avec annexes',
                'template' => 'pdf.templates.bon-commande-complet',
                'description' => 'BC avec tableau détaillé + conditions générales',
                'categorie' => 'Commercial',
                'ordre' => 2,
                'champs_variables' => [
                    'service' => ['source' => 'service.libelle', 'type' => 'uppercase'],
                    'numero_bca' => ['source' => 'numero', 'type' => 'text'],
                    'prestataire_nom' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ht' => ['source' => 'montant_ht', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'montant_ir' => ['source' => 'montant_ir', 'type' => 'money'],
                    'net_a_payer' => ['source' => 'net_a_percevoir', 'type' => 'money'],
                    'articles' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
            ],
            [
                'code' => 'bon_commande_preimprime',
                'type_document' => 'bon_commande',
                'est_defaut' => false,
                'nom' => 'BC Préimprimé (sans en-tête)',
                'template' => 'pdf.templates.bon-commande-preimprime',
                'description' => 'BC pour formulaire préimprimé',
                'categorie' => 'Commercial',
                'ordre' => 3,
                'champs_variables' => [
                    'numero_bca' => ['source' => 'numero', 'type' => 'text'],
                    'prestataire_nom' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'articles' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
            ],

            // ═══════════════════════════════════════════════════
            // ✅ BON DE COMMANDE RÉGIE / MENU DÉPENSE — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'bon_commande_regie_standard',
                'type_document' => 'bon_commande_regie',
                'est_defaut' => true,
                'nom' => 'BCR/BCM Standard',
                'template' => 'pdf.templates.bon-commande',
                'description' => 'Bon de commande Régie d\'Avance / Menu Dépense',
                'categorie' => 'Commercial',
                'ordre' => 1,
                'champs_variables' => [
                    'service' => ['source' => 'regieAvance.libelle', 'type' => 'uppercase'],
                    'numero_bca' => ['source' => 'numero', 'type' => 'text'],
                    'date_impression' => ['source' => 'created_at', 'type' => 'date', 'format' => 'd/m/Y'],
                    'prestataire_nom' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'prestataire_adresse' => ['source' => 'fournisseur.adresse', 'type' => 'text'],
                    'prestataire_tel' => ['source' => 'fournisseur.telephone', 'type' => 'text'],
                    'prestataire_contribuable' => ['source' => 'fournisseur.numero_contribuable', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ht' => ['source' => 'montant_ht', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'articles' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
                'entete_config' => [
                    'titre_document'    => 'BON DE COMMANDE RÉGIE D\'AVANCE',
                    'sous_direction_fr' => 'SERVICE DU BUDGET ET DE LA COMPTABILITÉ',
                    'sous_direction_en' => 'BUDGET AND ACCOUNTING DEPARTMENT',
                ],
            ],

            // ═══════════════════════════════════════════════════
            // AUTORISATION D'ENGAGEMENT — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'autorisation_engagement',
                'type_document' => 'autorisation_engagement',
                'est_defaut' => true,
                'nom' => 'Autorisation d\'Engagement Standard',
                'template' => 'pdf.templates.autorisation-engagement',
                'description' => 'Autorisation d\'engagement budgétaire',
                'categorie' => 'Budgétaire',
                'ordre' => 1,
                'champs_variables' => [
                    'montant' => ['source' => 'montant_total', 'type' => 'money'],
                    'reference' => ['source' => 'numero', 'type' => 'text'],
                    'date_signature' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'signataire' => ['source' => 'validateur.name', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire' => ['source' => 'instance_destinataire', 'type' => 'text'],
                    'exercice' => ['source' => 'exercice', 'type' => 'text'],
                    'chapitre' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.parent.code', 'type' => 'text'],
                    'article' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.parent.code', 'type' => 'text'],
                    'paragraphe' => ['source' => 'engagements.0.engageable.lignes.0.nomenclature.code', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'VISA DE L\'ORDONNATEUR', 'position' => 'center', 'largeur' => 100]],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // BORDEREAU D'ENGAGEMENT — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'bordereau_engagement',
                'type_document' => 'bordereau_engagement',
                'est_defaut' => true,
                'nom' => 'Bordereau d\'Engagement Standard',
                'template' => 'pdf.templates.bordereau-engagement',
                'description' => 'Bordereau récapitulatif des engagements',
                'categorie' => 'Budgétaire',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'exercice' => ['source' => 'exercice.annee', 'type' => 'text'],
                    'budget' => ['source' => 'budget.libelle', 'type' => 'text'],
                    'total' => ['source' => 'montant_total', 'type' => 'money'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'total_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'ORDONNATEUR', 'position' => 'right', 'largeur' => 90]],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // MÉMOIRE DE DÉPENSE — 2 variantes
            // ═══════════════════════════════════════════════════
            [
                'code' => 'memoire_depense',
                'type_document' => 'memoire_depense',
                'est_defaut' => true,
                'nom' => 'Mémoire de Dépense Standard',
                'template' => 'pdf.templates.memoire-depense',
                'description' => 'Mémoire de dépense avec en-tête HGY',
                'categorie' => 'Commercial',
                'ordre' => 1,
                'orientation' => 'landscape',
                'format_papier' => 'A4',
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'exercice' => ['source' => 'exercice', 'type' => 'text'],
                    'date_memoire' => ['source' => 'date_memoire', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'numero_decision' => ['source' => 'numero_decision', 'type' => 'text', 'default' => ''],
                    'numero_ce' => ['source' => 'numero_ce', 'type' => 'text', 'default' => ''],
                    'montant_ht' => ['source' => 'montant_ht', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'montant_ir' => ['source' => 'montant_ir', 'type' => 'money'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                    'signataire_nom' => ['source' => 'signataire_nom', 'type' => 'text', 'default' => ''],
                    'signataire_fonction' => ['source' => 'signataire_fonction', 'type' => 'text', 'default' => 'LE DIRECTEUR GÉNÉRAL'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_ttc_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'LE DIRECTEUR GÉNÉRAL', 'position' => 'right', 'largeur' => 80]],
                ],
                'options_pdf' => ['orientation' => 'landscape', 'format' => 'A4'],
            ],
            [
                'code' => 'memoire_depense_preimprime',
                'type_document' => 'memoire_depense',
                'est_defaut' => false,
                'nom' => 'Mémoire de Dépense Préimprimé',
                'template' => 'pdf.templates.memoire-depense-preimprime',
                'description' => 'MD pour formulaire préimprimé — sans en-tête',
                'categorie' => 'Commercial',
                'ordre' => 2,
                'orientation' => 'landscape',
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ttc' => ['source' => 'montant_ttc', 'type' => 'money'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_ttc']],
                ],
                'signature_config' => ['afficher' => false],
                'options_pdf' => ['orientation' => 'landscape', 'format' => 'A4'],
            ],

            // ═══════════════════════════════════════════════════
            // DÉCISION ADMINISTRATIVE — 2 variantes
            // ═══════════════════════════════════════════════════
            [
                'code' => 'decision_administrative',
                'type_document' => 'decision_administrative',
                'est_defaut' => true,
                'nom' => 'Décision Administrative Standard',
                'template' => 'pdf.templates.decision-administrative',
                'description' => 'DA avec calculs détaillés CNPS/IRNC',
                'categorie' => 'Administratif',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_decision' => ['source' => 'date_decision', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire_nom' => ['source' => 'personnel.nom_complet', 'type' => 'text', 'default' => ''],
                    'type_decision' => ['source' => 'typeDecision.libelle', 'type' => 'text'],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_cnps' => ['source' => 'montant_cnps', 'type' => 'money'],
                    'montant_irnc' => ['source' => 'montant_irnc', 'type' => 'money'],
                    'total_taxes' => ['source' => 'total_taxes', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                    'date_effet' => ['source' => 'date_effet', 'type' => 'date', 'format' => 'd/m/Y', 'default' => ''],
                    'date_fin' => ['source' => 'date_fin', 'type' => 'date', 'format' => 'd/m/Y', 'default' => ''],
                    'signataire' => ['source' => 'signataire', 'type' => 'text', 'default' => ''],
                ],
                'calculs' => [
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                    'montant_brut_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_brut']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'LE DIRECTEUR GÉNÉRAL', 'position' => 'right', 'largeur' => 80]],
                ],
            ],
            [
                'code' => 'decision_administrative_simple',
                'type_document' => 'decision_administrative',
                'est_defaut' => false,
                'nom' => 'Décision Administrative Simplifiée',
                'template' => 'pdf.templates.decision-administrative-simple',
                'description' => 'DA format simplifié — montants brut et net uniquement',
                'categorie' => 'Administratif',
                'ordre' => 2,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_decision' => ['source' => 'date_decision', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire_nom' => ['source' => 'personnel.nom_complet', 'type' => 'text', 'default' => ''],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                ],
                'calculs' => [
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'LE DIRECTEUR GÉNÉRAL', 'position' => 'right', 'largeur' => 80]],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // ORDONNANCE DE PAIEMENT — 3 variantes
            // ═══════════════════════════════════════════════════
            [
                'code' => 'ordonnance_paiement',
                'type_document' => 'ordonnance_paiement',
                'est_defaut' => true,
                'nom' => 'OP Standard',
                'template' => 'pdf.templates.ordonnance-paiement',
                'description' => 'Ordonnance de paiement avec en-tête complet',
                'categorie' => 'Paiement',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_emission' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'beneficiaire' => ['source' => 'beneficiaire_nom', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_ir' => ['source' => 'montant_ir', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                    'reference_ce' => ['source' => 'engagement.numero', 'type' => 'text', 'default' => ''],
                    'exercice' => ['source' => 'exercice.annee', 'type' => 'text'],
                    'mode_paiement' => ['source' => 'mode_paiement', 'type' => 'text', 'default' => 'virement'],
                ],
                'calculs' => [
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                    'montant_brut_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_brut']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'L\'ORDONNATEUR', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'LE COMPTABLE', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],
            [
                'code' => 'ordonnance_paiement_detaillee',
                'type_document' => 'ordonnance_paiement',
                'est_defaut' => false,
                'nom' => 'OP Détaillée (avec décompte)',
                'template' => 'pdf.templates.ordonnance-paiement-detaillee',
                'description' => 'OP avec tableau de décompte des retenues',
                'categorie' => 'Paiement',
                'ordre' => 2,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_emission' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'beneficiaire' => ['source' => 'beneficiaire_nom', 'type' => 'text'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_cnps' => ['source' => 'montant_cnps', 'type' => 'money'],
                    'montant_ir' => ['source' => 'montant_ir', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'total_retenues' => ['source' => 'total_retenues', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                    'numero_compte' => ['source' => 'beneficiaire.numero_compte', 'type' => 'text', 'default' => ''],
                    'banque' => ['source' => 'beneficiaire.banque', 'type' => 'text', 'default' => ''],
                ],
                'calculs' => [
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'L\'ORDONNATEUR', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'LE COMPTABLE', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],
            [
                'code' => 'ordonnance_paiement_preimprimee',
                'type_document' => 'ordonnance_paiement',
                'est_defaut' => false,
                'nom' => 'OP Préimprimée (sans en-tête)',
                'template' => 'pdf.templates.ordonnance-paiement-preimprimee',
                'description' => 'OP pour formulaire préimprimé',
                'categorie' => 'Paiement',
                'ordre' => 3,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_emission' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'beneficiaire' => ['source' => 'beneficiaire_nom', 'type' => 'text'],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => ['afficher' => false],
            ],

            // ═══════════════════════════════════════════════════
            // OPT — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'ordonnance_paiement_impot',
                'type_document' => 'ordonnance_paiement_impot',
                'est_defaut' => true,
                'nom' => 'OPT Standard',
                'template' => 'pdf.templates.ordonnance-paiement-impot',
                'description' => 'Ordonnance de paiement des retenues fiscales IR/TVA',
                'categorie' => 'Paiement',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_emission' => ['source' => 'date_emission', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'montant_ir' => ['source' => 'montant_ir', 'type' => 'money'],
                    'montant_tva' => ['source' => 'montant_tva', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                    'reference_op' => ['source' => 'op_principale.numero', 'type' => 'text', 'default' => ''],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'L\'ORDONNATEUR', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'LE COMPTABLE', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // DÉCISION PRÉVISIONNELLE — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code' => 'decision_previsionnelle',
                'type_document' => 'decision_previsionnelle',
                'est_defaut' => true,
                'nom' => 'Décision Prévisionnelle',
                'template' => 'pdf.templates.decision-previsionnelle',
                'description' => 'Simulation DA — sans impact budget',
                'categorie' => 'Administratif',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_decision' => ['source' => 'date_decision', 'type' => 'date', 'format' => 'd/m/Y'],
                    'objet' => ['source' => 'objet', 'type' => 'text'],
                    'beneficiaire_nom' => ['source' => 'personnel.nom_complet', 'type' => 'text', 'default' => ''],
                    'type_decision' => ['source' => 'typeDecision.libelle', 'type' => 'text'],
                    'montant_brut' => ['source' => 'montant_brut', 'type' => 'money'],
                    'montant_net' => ['source' => 'montant_net', 'type' => 'money'],
                ],
                'calculs' => [
                    'montant_net_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_net']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [['titre' => 'LE DIRECTEUR GÉNÉRAL', 'position' => 'right', 'largeur' => 80]],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // COMPTABILITÉ MATIÈRES — documents existants
            // ═══════════════════════════════════════════════════
            [
                'code' => 'pv_reception',
                'type_document' => 'pv_reception',
                'est_defaut' => true,
                'nom' => 'PV de Réception Standard',
                'template' => 'pdf.templates.pv-reception',
                'description' => 'PV de réception des biens et services',
                'categorie' => 'Comptabilité Matières',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_reception' => ['source' => 'date_reception', 'type' => 'date', 'format' => 'd/m/Y'],
                    'fournisseur' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'numero_bordereau' => ['source' => 'numero_bordereau_livraison', 'type' => 'text', 'default' => ''],
                    'numero_facture' => ['source' => 'numero_facture', 'type' => 'text', 'default' => ''],
                    'montant_facture' => ['source' => 'montant_facture', 'type' => 'money'],
                    'president_commission' => ['source' => 'presidentCommission.name', 'type' => 'text'],
                    'comptable_matieres' => ['source' => 'comptableMatieres.name', 'type' => 'text'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_facture']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'LE PRESTATAIRE', 'position' => 'left', 'largeur' => 22],
                        ['titre' => 'SERVICE TECHNIQUE', 'position' => 'left', 'largeur' => 22],
                        ['titre' => 'COMPTABLE MATIÈRES', 'position' => 'right', 'largeur' => 22],
                        ['titre' => 'ORDONNATEUR MATIÈRES', 'position' => 'right', 'largeur' => 22],
                    ],
                ],
            ],
            [
                'code' => 'ordre_entree',
                'type_document' => 'ordre_entree',
                'est_defaut' => true,
                'nom' => 'Ordre d\'Entrée Standard',
                'template' => 'pdf.templates.ordre-entree',
                'description' => 'OE — prise en charge comptable',
                'categorie' => 'Comptabilité Matières',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_oe' => ['source' => 'date_oe', 'type' => 'date', 'format' => 'd/m/Y'],
                    'fournisseur' => ['source' => 'fournisseur.raison_sociale', 'type' => 'text'],
                    'numero_reception' => ['source' => 'reception.numero', 'type' => 'text'],
                    'montant_total' => ['source' => 'montant_total', 'type' => 'money'],
                    'comptable_matieres' => ['source' => 'comptableMatieres.name', 'type' => 'text'],
                    'ordonnateur' => ['source' => 'ordonnateur.name', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'LE COMPTABLE MATIÈRES', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'L\'ORDONNATEUR MATIÈRES', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],
            [
                'code' => 'bon_sortie_provisoire',
                'type_document' => 'bon_sortie_provisoire',
                'est_defaut' => true,
                'nom' => 'BSP Standard',
                'template' => 'pdf.templates.bon-sortie-provisoire',
                'description' => 'Bon de Sortie Provisoire cosigné 3 parties',
                'categorie' => 'Comptabilité Matières',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_bsp' => ['source' => 'date_bsp', 'type' => 'date', 'format' => 'd/m/Y'],
                    'service_demandeur' => ['source' => 'service_demandeur', 'type' => 'text'],
                    'demandeur' => ['source' => 'demandeur.name', 'type' => 'text'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                    'comptable_matieres' => ['source' => 'comptableMatieres.name', 'type' => 'text'],
                    'ordonnateur' => ['source' => 'ordonnateur.name', 'type' => 'text'],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'LE DEMANDEUR', 'position' => 'left', 'largeur' => 30],
                        ['titre' => 'LE COMPTABLE MATIÈRES', 'position' => 'center', 'largeur' => 30],
                        ['titre' => 'L\'ORDONNATEUR MATIÈRES', 'position' => 'right', 'largeur' => 30],
                    ],
                ],
            ],
            [
                'code' => 'ordre_sortie',
                'type_document' => 'ordre_sortie',
                'est_defaut' => true,
                'nom' => 'Ordre de Sortie Standard',
                'template' => 'pdf.templates.ordre-sortie',
                'description' => 'OS cosigné comptable + ordonnateur',
                'categorie' => 'Comptabilité Matières',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_os' => ['source' => 'date_os', 'type' => 'date', 'format' => 'd/m/Y'],
                    'periodicite' => ['source' => 'periodicite', 'type' => 'text'],
                    'montant_total' => ['source' => 'montant_total', 'type' => 'money'],
                    'lignes' => ['source' => 'lignes', 'type' => 'array'],
                    'comptable_matieres' => ['source' => 'comptableMatieres.name', 'type' => 'text'],
                    'ordonnateur' => ['source' => 'ordonnateur.name', 'type' => 'text'],
                ],
                'calculs' => [
                    'montant_lettres' => ['fonction' => 'nombre_en_lettres', 'params' => ['_raw.montant_total']],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'LE COMPTABLE MATIÈRES', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'L\'ORDONNATEUR MATIÈRES', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],
            [
                'code' => 'fiche_detenteur',
                'type_document' => 'fiche_detenteur',
                'est_defaut' => true,
                'nom' => 'Fiche de Détenteur Standard',
                'template' => 'pdf.templates.fiche-detenteur',
                'description' => 'Fiche d\'affectation bien durable',
                'categorie' => 'Comptabilité Matières',
                'ordre' => 1,
                'champs_variables' => [
                    'numero' => ['source' => 'numero', 'type' => 'text'],
                    'date_affectation' => ['source' => 'date_affectation', 'type' => 'date', 'format' => 'd/m/Y'],
                    'article_designation' => ['source' => 'article.designation', 'type' => 'text'],
                    'article_code' => ['source' => 'article.code', 'type' => 'text'],
                    'numero_serie' => ['source' => 'numero_serie', 'type' => 'text', 'default' => ''],
                    'detenteur_nom' => ['source' => 'detenteur.name', 'type' => 'text'],
                    'service_detenteur' => ['source' => 'service_detenteur', 'type' => 'text'],
                    'etat_affectation' => ['source' => 'etat_affectation', 'type' => 'text'],
                    'comptable_matieres' => ['source' => 'comptableMatieres.name', 'type' => 'text'],
                ],
                'signature_config' => [
                    'afficher' => true,
                    'signatures' => [
                        ['titre' => 'LE DÉTENTEUR', 'position' => 'left', 'largeur' => 45],
                        ['titre' => 'LE COMPTABLE MATIÈRES', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════
            // ✅ NOUVEAU — EXPRESSION DE BESOIN — 1 variante
            // ═══════════════════════════════════════════════════
            [
                'code'          => 'expression_besoin',
                'type_document' => 'expression_besoin',
                'est_defaut'    => true,
                'nom'           => 'Expression de Besoin Standard',
                'template'      => 'pdf.templates.expression-besoin',
                'description'   => 'Expression des besoins — Services (Pharmacie, etc.)',
                'categorie'     => 'Comptabilité Matières',
                'ordre'         => 10,
                'format_papier' => 'A4',
                'orientation'   => 'portrait',
                'champs_variables' => [
                    'numero'          => ['source' => 'numero',                  'type' => 'text'],
                    'date_expression' => ['source' => 'date_expression',         'type' => 'date', 'format' => 'd/m/Y'],
                    'service'         => ['source' => 'serviceDemandeur.nom',    'type' => 'uppercase'],
                    'objet'           => ['source' => 'objet',                   'type' => 'text'],
                    'responsable'     => ['source' => 'responsableService.name', 'type' => 'text'],
                    'comptable'       => ['source' => 'comptableMatieres.name',  'type' => 'text'],
                    'signataire_dg'   => ['source' => 'signataireDg.name',       'type' => 'text'],
                    'lignes'          => ['source' => 'lignes',                  'type' => 'array'],
                ],
                'calculs' => [],
                'signature_config' => [
                    'afficher'   => true,
                    'signatures' => [
                        ['titre' => 'LE CHEF DE SERVICE', 'position' => 'right', 'largeur' => 45],
                    ],
                ],
                'options_pdf' => [
                    'format_papier' => 'A4',
                    'orientation'   => 'portrait',
                ],
                'entete_config' => [
                    'sous_direction_fr' => 'DIRECTION MEDICALE',
                    'sous_direction_en' => 'MEDICAL DEPARTMENT',
                ],
            ],
        ];

        // ═══════════════════════════════════════════════════════
        // ✅ SEEDING — préserve les personnalisations entete_config
        // ═══════════════════════════════════════════════════════
        foreach ($etats as $etat) {
            $enteteConfigInitial = $etat['entete_config'] ?? null;
            unset($etat['entete_config']);

            $config = EtatConfig::updateOrCreate(
                ['code' => $etat['code']],
                $etat
            );

            if ($enteteConfigInitial !== null) {
                $enteteActuel = $config->entete_config;
                if ($config->wasRecentlyCreated || empty($enteteActuel)) {
                    $config->update(['entete_config' => $enteteConfigInitial]);
                }
            }
        }

        $groupes = collect($etats)->groupBy('type_document');
        $this->command->info('');
        $this->command->info('✅ ' . count($etats) . ' états créés/mis à jour — ' . $groupes->count() . ' types de documents');
        $this->command->info('');

        foreach ($groupes as $type => $variantes) {
            $this->command->line("  📄 {$type} : " . $variantes->count() . " variante(s)");
            foreach ($variantes as $v) {
                $defaut = $v['est_defaut'] ? ' ⭐' : '';
                $this->command->line("     → [{$v['code']}] {$v['nom']}{$defaut}");
            }
        }
    }
}
