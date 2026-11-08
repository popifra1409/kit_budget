<?php
// config/audit.php

/*
|--------------------------------------------------------------------------
| Audit (journal d'activite) — perimetre par module
|--------------------------------------------------------------------------
| Chaque modele liste ici :
|  - est rattache a un MODULE (colonne et filtre "Module" du journal, y compris
|    pour l'historique deja enregistre) ;
|  - recoit un LIBELLE lisible (colonne "Type") ;
|  - est JOURNALISE automatiquement par App\Observers\AuditObserver,
|    sauf s'il l'est deja nativement (trait LogsActivity) ou s'il est exclu.
|
| Ajouter un nouveau modele : une ligne dans le bon module, rien d'autre.
| Verifier la couverture : php artisan audit:couverture
*/

return [

    // Panel Filament qui heberge le journal (seul endroit ou il est visible)
    'panel' => 'admin',

    // Categorie (log_name) des entrees ecrites par l'observateur
    'log_name' => 'audit',

    // Journaliser aussi les operations lancees en console (seeders, commandes, files d'attente).
    // Desactive par defaut : un seeder cree des centaines d'entrees sans interet d'audit.
    // Les commandes de correction de donnees tracent deja elles-memes leurs operations.
    'journaliser_console' => env('AUDIT_CONSOLE', false),

    /*
    | Conservation du journal : les deux regles doivent rester coerentes entre elles.
    |
    | archivage_mois  age ou activity:archive bascule une entree dans activity_log_archives
    | purge_jours     age ou logs:clean supprime definitivement une entree de la table active
    |
    | La purge ne peut jamais atteindre une entree plus vieille que son propre seuil avant
    | que l'archivage ait pu la deplacer : sinon la duree de purge detruit la preuve au lieu
    | de la conserver. C'est ce que verifie le garde-fou de ViderAnciensLogs.
    |
    | La duree de purge peut etre surchargee par etablissement (colonne
    | duree_conservation_logs de parametres_structure) : la plus longue fait loi, la valeur
    | de ce fichier n'est que le plancher quand aucun etablissement ne l'a renseigne.
    */
    'conservation' => [
        'archivage_mois' => 6,
        'purge_jours'    => 365,
    ],

    // Champs jamais enregistres dans le journal (secrets, horodatages techniques)
    'champs_exclus' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ],

    // Champs jamais enregistres, par prefixe : le gel des etats imprimes (snapshot_*)
    // recopie ce que le papier dit deja. Ce n'est pas une decision de gestion, et sa
    // valeur de verite se lit dans le champ metier qui l'a fait bouger.
    'champs_exclus_prefixes' => [
        'snapshot_',
    ],

    // Longueur maximale d'une valeur texte conservee dans le journal
    'longueur_max_valeur' => 500,

    // Modeles NON journalises par l'observateur (bruit sans valeur d'audit)
    'modeles_exclus' => [
        'App\Models\LigneBudgetaire', // recalculee a chaque engagement/paiement ; les operations sources sont deja tracees
        'App\Models\FicheStock',      // registre des mouvements : c'est deja une trace
        'App\Models\ActivityLog',     // le journal lui-meme
    ],

    'modules' => [

        'budget' => [
            'libelle' => 'Budget',
            'modeles' => [
                'App\Models\Budget'                    => 'Budget',
                'App\Models\LigneBudgetaire'           => 'Ligne budgétaire',
                'App\Models\NomenclatureBudgetaire'    => 'Nomenclature budgétaire',
                'App\Models\GroupeNomenclature'        => 'Groupe de nomenclature',
                'App\Models\Engagement'                => 'Engagement',
                'App\Models\LigneEngagement'           => "Ligne d'engagement",
                'App\Models\Avenant'                   => 'Avenant',
                'App\Models\BonCommande'               => 'Bon de commande',
                'App\Models\LigneBonCommande'          => 'Ligne de bon de commande',
                'App\Models\DecisionAdministrative'    => 'Décision administrative',
                'App\Models\MenuDepenseDecision'       => 'Menu de dépense (décision)',
                'App\Models\OrdonnancePaiement'        => 'Ordonnance de paiement',
                'App\Models\BordereauEngagement'       => "Bordereau d'engagement",
                'App\Models\BordereauEngagementLigne'  => "Ligne de bordereau d'engagement",
                'App\Models\MemoireDepense'            => 'Mémoire de dépense',
                'App\Models\LigneMemoireDepense'       => 'Ligne de mémoire de dépense',
                'App\Models\FactureProforma'           => 'Facture proforma',
                'App\Models\LigneFactureProforma'      => 'Ligne de facture proforma',
                'App\Models\VirementBudgetaire'        => 'Virement budgétaire',
                'App\Models\CollectifBudgetaire'       => 'Collectif budgétaire',
                'App\Models\MouvementCollectif'        => 'Mouvement de collectif',
                'App\Models\PrevisionRecette'          => 'Prévision de recette',
                'App\Models\LignePrevisionRecette'     => 'Ligne de prévision de recette',
                'App\Models\PrevisionRecetteMensuelle' => 'Prévision de recette mensuelle',
                'App\Models\RecetteReelle'             => 'Recette réelle',
                'App\Models\PrevisionBudgetProgramme'  => 'Prévision budget-programme',
                'App\Models\RegieAvance'               => "Régie d'avance",
                'App\Models\LigneRegieAvance'          => "Ligne de régie d'avance",
                'App\Models\BonCommandeRegie'          => 'Bon de commande (régie)',
                'App\Models\LigneBonCommandeRegie'     => 'Ligne de bon de commande (régie)',
                'App\Models\DecaissementRegie'         => 'Décaissement (régie)',
                'App\Models\DepenseRegie'              => 'Dépense (régie)',
                'App\Models\LigneDepenseRegie'         => 'Ligne de dépense (régie)',
                'App\Models\ProvisionLigneRegie'       => 'Provision (régie)',
                'App\Models\ProvisionConsommation'     => 'Consommation de provision',
                'App\Models\TypeEngagement'            => "Type d'engagement",
                'App\Models\TypeDecision'              => 'Type de décision',
                'App\Models\ModePaiement'              => 'Mode de paiement',
                'App\Models\TauxIr'                    => "Taux d'IR",
                'App\Models\RegimeFiscal'              => 'Régime fiscal',
                'App\Models\EtatConfig'                => "Configuration d'état",
                'App\Models\ClotureExercice'        => "Clôture d'exercice",
                'App\Models\ClotureLigne'           => 'Ligne de clôture',
                'App\Models\TiersRecette'           => 'Débiteur / payeur',
                'App\Models\NatureServiceFait'  => 'Nature de service fait',
                'App\Models\PreuveServiceFait'  => 'Preuve de service fait',
                'App\Models\Liquidation'        => 'Liquidation',
                'App\Models\LiquidationPreuve'  => 'Preuve de liquidation',
                'App\Models\PaiementExceptionnel' => 'Paiement exceptionnel',
            ],
        ],

        'comptable' => [
            'libelle' => 'Comptabilité matières',
            'modeles' => [
                'App\Models\Article'                  => 'Article',
                'App\Models\CategorieArticle'         => "Catégorie d'article",
                'App\Models\UniteMesure'              => 'Unité de mesure',
                'App\Models\Conditionnement'          => 'Conditionnement',
                'App\Models\Stock'                    => 'Stock',
                'App\Models\FicheStock'               => 'Fiche de stock',
                'App\Models\ExpressionBesoin'         => 'Expression de besoin',
                'App\Models\LigneExpressionBesoin'    => "Ligne d'expression de besoin",
                'App\Models\FicheConsolidationBesoin' => 'Fiche de consolidation des besoins',
                'App\Models\Reception'                => 'Réception',
                'App\Models\LigneReception'           => 'Ligne de réception',
                'App\Models\OrdreEntree'              => "Ordre d'entrée",
                'App\Models\BordereauMouvement'       => 'Bordereau de mouvement',
                'App\Models\MouvementBordereau'       => 'Mouvement de bordereau',
            ],
        ],

        'marches' => [
            'libelle' => 'Marchés publics',
            'modeles' => [
                'App\Models\Fournisseur'           => 'Fournisseur',
                'App\Models\ParametresFournisseur' => 'Paramètres fournisseur',
                'App\Models\DossierFournisseur'    => 'Dossier fournisseur',
                'App\Models\PieceDossier'          => 'Pièce de dossier',
                'App\Models\ReferenceMercuriale'   => 'Référence mercuriale',
                'App\Models\Marche'                => 'Marché',
                'App\Models\Offre'                 => 'Offre',
                'App\Models\Evaluation'            => 'Note d\'évaluation',
                'App\Models\TypeProcedure'         => 'Type de procédure',
                'App\Models\TypeContrat'           => 'Type de contrat',
                'App\Models\CategorieMarche'       => 'Catégorie de marché',
                'App\Models\ElementEvaluationDao'  => 'Élément d\'évaluation du DAO',
                'App\Models\OrdreService'          => 'Ordre de service',
                'App\Models\TypeOrdreService'      => 'Type d\'ordre de service',
                'App\Models\Commission'            => 'Commission',
                'App\Models\CommissionMembre'      => 'Membre de commission',
                // Étape E : la fiche qui constate la séance, et la case de visa de
                // chaque membre. Les deux s'écrivent, donc les deux se relisent.
                'App\Models\FichePresence'         => 'Fiche de présence',
                'App\Models\FichePresenceMembre'   => 'Émargement de la fiche de présence',
            ],
        ],

        'planification' => [
            'libelle' => 'Planification stratégique',
            'modeles' => [
                'App\Models\CspMinistereSante'  => 'Cadre stratégique (CSP)',
                'App\Models\PlanStrategiqueEp'  => 'Plan stratégique (PSP)',
                'App\Models\SousProgrammeEp'    => 'Sous-programme',
                'App\Models\CadreLogique'       => 'Cadre logique',
                'App\Models\ObjectifPrincipal'  => 'Objectif principal',
                'App\Models\ObjectifSpecifique' => 'Objectif spécifique',
                'App\Models\Programme'          => 'Programme',
                'App\Models\Action'             => 'Action',
                'App\Models\Activite'           => 'Activité',
                'App\Models\Tache'              => 'Tâche',
                'App\Models\Extrant'            => 'Extrant',
                'App\Models\Indicateur'         => 'Indicateur',
                'App\Models\ValeurIndicateur'   => "Valeur d'indicateur",
            ],
        ],

        'programmation' => [
            'libelle' => 'Programmation',
            'modeles' => [
                'App\Models\PpaExercice'  => 'PPA',
                'App\Models\CbmtExercice' => 'CBMT',
                'App\Models\CbmtLigne'    => 'Ligne de CBMT',
                'App\Models\CdmtExercice' => 'CDMT',
                'App\Models\CdmtLigne'    => 'Ligne de CDMT',
            ],
        ],

        'suivi_evaluation' => [
            'libelle' => 'Suivi et évaluation',
            'modeles' => [
                'App\Models\RapportActivitePeriodique' => "Rapport d'activité",
                'App\Models\RapportActiviteLigne'      => "Ligne de rapport d'activité",
                'App\Models\RapportAnnuelPerformance'  => 'Rapport annuel de performance',
            ],
        ],

        'administration' => [
            'libelle' => 'Administration',
            'modeles' => [
                'App\Models\User'                => 'Utilisateur',
                // Le morph map relie l'alias « role » au rôle Spatie (config/permission.php),
                // et c'est lui que le écran Rôles journalise : App\Models\Role, qui ne sert
                // qu'à l'alias « role_application », ne correspondait à aucune des 472 lignes
                // du journal et laissait leur module vide.
                'Spatie\Permission\Models\Role'   => 'Rôle',
                'App\Models\Personnel'           => 'Personnel',
                'App\Models\Service'             => 'Service',
                'App\Models\Exercice'            => 'Exercice',
                'App\Models\ParametresStructure' => 'Paramètres de la structure',
                'App\Models\Transmission'        => 'Transmission',
                'App\Models\ParametreExecution'  => "Paramètre d'exécution",
            ],
        ],
    ],
];
