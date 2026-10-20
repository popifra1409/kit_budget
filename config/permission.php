<?php

return [

    'models' => [

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        /*
         * Change this if you want to name the related pivots other than defaults
         */
        'role_pivot_key' => null, // default 'role_id',
        'permission_pivot_key' => null, // default 'permission_id',

        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         */

        'model_morph_key' => 'model_id',

        /*
         * Change this if you want to use the teams feature and your related model's
         * foreign key is other than `team_id`.
         */

        'team_foreign_key' => 'team_id',
    ],

    /*
     * When set to true, the method for checking permissions will be registered on the gate.
     * Set this to false if you want to implement custom logic for checking permissions.
     */

    'register_permission_check_method' => true,

    /*
     * When set to true, Laravel\Octane\Events\OperationTerminated event listener will be registered
     * this will refresh permissions on every TickTerminated, TaskTerminated and RequestTerminated
     * NOTE: This should not be needed in most cases, but an Octane/Vapor combination benefited from it.
     */
    'register_octane_reset_listener' => false,

    /*
     * Events will fire when a role or permission is assigned/unassigned:
     * \Spatie\Permission\Events\RoleAttached
     * \Spatie\Permission\Events\RoleDetached
     * \Spatie\Permission\Events\PermissionAttached
     * \Spatie\Permission\Events\PermissionDetached
     *
     * To enable, set to true, and then create listeners to watch these events.
     */
    'events_enabled' => false,

    /*
     * Teams Feature.
     * When set to true the package implements teams using the 'team_foreign_key'.
     * If you want the migrations to register the 'team_foreign_key', you must
     * set this to true before doing the migration.
     * If you already did the migration then you must make a new migration to also
     * add 'team_foreign_key' to 'roles', 'model_has_roles', and 'model_has_permissions'
     * (view the latest version of this package's migration file)
     */

    'teams' => false,

    /*
     * The class to use to resolve the permissions team id
     */
    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    /*
     * Passport Client Credentials Grant
     * When set to true the package will use Passports Client to check permissions
     */

    'use_passport_client_credentials' => false,

    /*
     * When set to true, the required permission names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     */

    'display_permission_in_exception' => false,

    /*
     * When set to true, the required role names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     */

    'display_role_in_exception' => false,

    /*
     * By default wildcard permission lookups are disabled.
     * See documentation to understand supported syntax.
     */

    'enable_wildcard_permission' => false,

    /*
     * The class to use for interpreting wildcard permissions.
     * If you need to modify delimiters, override the class and specify its name here.
     */
    // 'wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    /* Cache-specific settings */

    'cache' => [

        /*
         * By default all permissions are cached for 24 hours to speed up performance.
         * When permissions or roles are updated the cache is flushed automatically.
         */

        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all permissions.
         */

        'key' => 'spatie.permission.cache',

        /*
         * You may optionally indicate a specific cache driver to use for permission and
         * role caching using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         */

        'store' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue des permissions de l'application (SIGB), par MODULE puis par RUBRIQUE
    |--------------------------------------------------------------------------
    | Ajout propre à l'application (ignoré par le paquet Spatie). Source unique utilisée par :
    |  - RolePermissionSeeder : création des permissions (non destructive) ;
    |  - RoleResource : présentation des droits (un onglet par module, une rubrique par domaine) ;
    |  - User::canAccessPanel() : permission d'accès de chaque panneau ('acces').
    | Pour chaque rubrique : 'crud' (ressources → view_any, view, create, update, delete)
    | et 'speciales' (permissions d'action). Une permission utilisée dans le code mais absente
    | d'ici est créée par le seeder et apparaît dans l'onglet « Autres » de l'écran des rôles.
    */
    'catalogue' => [

        'modules' => [

            // ════════════════════════════════════════════════════════
            'portal' => [
                'libelle' => 'Portail',
                'icone'   => 'heroicon-o-home',
                'acces'   => 'access_module_portal',
                'rubriques' => [],
            ],

            // ════════════════════════════════════════════════════════
            'budget' => [
                'libelle' => 'Gestion budgétaire',
                'icone'   => 'heroicon-o-banknotes',
                'acces'   => 'access_module_budget',
                'rubriques' => [
                    'Exercices et budgets' => [
                        'crud' => ['exercice', 'budget', 'nomenclature_budgetaire', 'groupe_nomenclature', 'service', 'personnel'],
                        'speciales' => [
                            'ouvrir_exercice',
                            'cloturer_exercice',
                            'reconduire_exercice',
                            'activer_budget',
                            'adopter_budget',
                            'cloturer_budget',
                            'activer_service',
                            'view_budget_programme',
                            'saisir_previsions_budget_programme',
                            'exporter_budget_programme',
                            'supprimer_ligne_budgetaire_adoptee',
                        ],
                    ],
                    'Bons de commande et marchés' => [
                        'crud' => ['bon_commande', 'type_engagement', 'facture_proforma', 'reference_mercuriale', 'regime_fiscal'],
                        'speciales' => [
                            'valider_bon_commande',
                            'annuler_bon_commande',
                            'force_update_bon_commande',
                            'engager_bon_commande',
                            'desengager_bon_commande',
                            'recuperer_bon_commande',
                            'activer_reference_mercuriale',
                            'generer_bon_commande_expression_besoin',
                        ],
                    ],
                    'Décisions administratives et mémoires' => [
                        'crud' => ['decision_administrative', 'type_decision', 'memoire_depense'],
                        'speciales' => [
                            'valider_decision_administrative',
                            'annuler_decision_administrative',
                            'force_update_decision_administrative',
                            'engager_decision_administrative',
                            'desengager_decision_administrative',
                            'recuperer_decision_administrative',
                            'valider_memoire_depense',
                            'devalider_memoire_depense',
                            'transformer_memoire_depense_en_da',
                        ],
                    ],
                    'Engagements' => [
                        'crud' => ['engagement', 'bordereau_engagement', 'avenant_engagement', 'fiche_controle_engagements'],
                        'speciales' => [
                            'valider_engagement',
                            'annuler_engagement',
                            'creer_avenant_engagement',
                            'transmettre_bordereau_engagement',
                            'receptionner_bordereau_engagement',
                            'valider_bordereau_engagement',
                            'rejeter_bordereau_engagement',
                            'retourner_bordereau_engagement',
                            'telecharger_bordereau_engagement',
                            'override_bordereau_engagement',
                            'gerer_engagements_bordereau',
                            'generer_pdf_fiche_controle_engagements',
                        ],
                    ],
                    'Liquidation et paiement' => [
                        'crud' => ['ordonnance_paiement'],
                        'speciales' => [
                            'view_any_liquidation',
                            'create_liquidation',
                            'certifier_service_fait',
                            'liquider_depense',
                            'viser_liquidation',
                            'gerer_natures_service_fait',
                            'emettre_ordonnance_paiement',
                            'viser_ordonnance_paiement',
                            'valider_ordonnance_paiement',
                            'payer_ordonnance_paiement',
                            'marquer_payee_ordonnance_paiement',
                            'annuler_ordonnance_paiement',
                            'creer_op_depuis_engagement',
                            'telecharger_ordonnance_paiement',
                            'override_ordonnance_paiement',
                            'view_any_paiement_exceptionnel',
                            'create_paiement_exceptionnel',
                            'autoriser_paiement_exceptionnel',
                            'payer_paiement_exceptionnel',
                            'regulariser_paiement_exceptionnel',
                        ],
                    ],
                    'Mouvements de crédits et collectifs' => [
                        'crud' => ['virement_budgetaire', 'collectif_budgetaire'],
                        'speciales' => [
                            'annuler_execution_mouvement_credit',
                            'gerer_collectif_budgetaire',
                            'valider_collectif_budgetaire',
                            'adopter_collectif_budgetaire',
                            'annuler_collectif_budgetaire',
                        ],
                    ],
                    'Recettes' => [
                        'crud' => ['prevision_recette', 'recette_reelle'],
                        'speciales' => ['supprimer_ligne_prevision_adoptee'],
                    ],
                    "Régies d'avance et menus de dépenses" => [
                        'crud' => ['regie_avance', 'menu_depense', 'decaissement_regie', 'depense_regie', 'bon_commande_regie'],
                        'speciales' => [
                            'valider_regie_avance',
                            'suspendre_regie_avance',
                            'cloturer_regie_avance',
                            'reapprovisionner_regie_avance',
                            'valider_menu_depense',
                            'suspendre_menu_depense',
                            'cloturer_menu_depense',
                            'reapprovisionner_menu_depense',
                            'valider_decaissement_regie',
                            'verser_decaissement_regie',
                            'apurer_decaissement_regie',
                            'valider_depense_regie',
                            'annuler_depense_regie',
                            'valider_bon_commande_regie',
                            'annuler_bon_commande_regie',
                            'imprimer_etat_retenues_regie',
                            'imprimer_compte_emploi_regie',
                        ],
                    ],
                    'Fournisseurs et dossiers' => [
                        'crud' => ['fournisseur', 'dossier_fournisseur', 'piece_dossier'],
                        'speciales' => [
                            'blacklister_fournisseur',
                            'toggle_fournisseur',
                            'cloturer_dossier_fournisseur',
                            'annuler_dossier_fournisseur',
                            'ajouter_piece_dossier',
                            'supprimer_piece_dossier',
                            'valider_piece_dossier',
                            'invalider_piece_dossier',
                            'telecharger_piece_dossier',
                            'view_all_dossiers',
                            'view_my_dossiers',
                        ],
                    ],
                    'Transmissions' => [
                        'crud' => ['transmission'],
                        'speciales' => [
                            'transmettre_document',
                            'retourner_document',
                            'cloturer_transmission',
                            'annuler_transmission',
                            'view_all_transmissions',
                            'view_my_transmissions',
                        ],
                    ],
                    'Clôture et reddition des comptes' => [
                        'crud' => [],
                        'speciales' => [
                            'view_any_cloture_exercice',
                            'gerer_cloture_exercice',
                            'arreter_reports_credits',
                            'enregistrer_avis_ca',
                            'operer_hors_periode_execution',
                            'view_compte_administratif',
                        ],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════════
            'comptable' => [
                'libelle' => 'Comptabilité matières',
                'icone'   => 'heroicon-o-archive-box',
                'acces'   => 'access_module_comptable',
                'rubriques' => [
                    'Articles et stocks' => [
                        'crud' => ['article', 'stock', 'fiche_stock', 'unite_mesure', 'conditionnement', 'categorie_article'],
                        'speciales' => ['ajuster_stock', 'inventorier_stock'],
                    ],
                    'Expressions de besoins' => [
                        'crud' => ['expression_besoin', 'fiche_consolidation_besoin'],
                        'speciales' => [
                            'soumettre_expression_besoin',
                            'valider_expression_besoin',
                            'rejeter_expression_besoin',
                            'signer_expression_besoin',
                            'consolider_expression_besoin',
                            'generer_expression_besoin_bon_commande',
                        ],
                    ],
                    "Réceptions et ordres d'entrée" => [
                        'crud' => ['reception', 'ordre_entree'],
                        'speciales' => ['signer_pv_reception', 'integrer_reception_stock', 'signer_ordre_entree', 'transmettre_ordre_entree'],
                    ],
                    'Sorties (BSF, BSP, ordres de sortie)' => [
                        'crud' => ['bon_sortie_fourniture', 'bon_sortie_provisoire', 'ordre_sortie'],
                        'speciales' => [
                            'soumettre_bon_sortie_fourniture',
                            'signer_bsp_demandeur',
                            'signer_bsp_comptable',
                            'signer_bsp_ordonnateur',
                            'executer_bon_sortie_provisoire',
                            'signer_ordre_sortie',
                            'transmettre_ordre_sortie',
                        ],
                    ],
                    'Détenteurs et consommations' => [
                        'crud' => ['fiche_detenteur', 'registre_consommation'],
                        'speciales' => ['retourner_fiche_detenteur'],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════════
            'marches' => [
                'libelle' => 'Marchés publics',
                'icone'   => 'heroicon-o-building-office',
                'acces'   => 'access_module_marches',
                'rubriques' => [],
            ],

            // ════════════════════════════════════════════════════════
            'planification' => [
                'libelle' => 'Planification stratégique',
                'icone'   => 'heroicon-o-map',
                'acces'   => 'access_module_planification',
                'rubriques' => [
                    'Plan stratégique et sous-programmes' => [
                        'crud' => ['csp_ministere_sante', 'plan_strategique_ep', 'sous_programme_ep'],
                        'speciales' => [
                            'transmettre_plan_strategique_ep',
                            'valider_plan_strategique_ep',
                            'retourner_plan_strategique_ep',
                            'cloturer_plan_strategique_ep',
                            'transmettre_sous_programme_ep',
                            'valider_sous_programme_ep',
                            'retourner_sous_programme_ep',
                        ],
                    ],
                    'Programmes, actions, activités, tâches' => [
                        'crud' => ['programme', 'action', 'activite', 'tache'],
                        'speciales' => ['transmettre_activite', 'valider_activite', 'retourner_activite'],
                    ],
                    'Indicateurs et extrants' => [
                        'crud' => ['indicateur', 'extrant'],
                        'speciales' => ['saisir_valeur_indicateur', 'valider_valeur_indicateur'],
                    ],
                    'Revue des libellés' => [
                        'crud' => [],
                        'speciales' => ['view_arborescence_libelles', 'view_all_arborescence_libelles', 'exporter_arborescence_libelles'],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════════
            'programmation' => [
                'libelle' => 'Programmation (PPA, CBMT, CDMT)',
                'icone'   => 'heroicon-o-calendar-days',
                'acces'   => 'access_module_programmation',
                'rubriques' => [
                    'PPA, CBMT et CDMT' => [
                        'crud' => ['ppa_exercice', 'cbmt_exercice', 'cdmt_exercice'],
                        'speciales' => [
                            'transmettre_ppa_exercice',
                            'valider_ppa_exercice',
                            'retourner_ppa_exercice',
                            'transmettre_cbmt_exercice',
                            'valider_cbmt_exercice',
                            'retourner_cbmt_exercice',
                            'transmettre_cdmt_exercice',
                            'valider_cdmt_exercice',
                            'retourner_cdmt_exercice',
                        ],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════════
            'suivi_evaluation' => [
                'libelle' => 'Suivi et évaluation',
                'icone'   => 'heroicon-o-chart-bar-square',
                'acces'   => 'access_module_suivi_evaluation',
                'rubriques' => [
                    'Rapports de performance' => [
                        'crud' => ['rapport_activite_periodique', 'rapport_annuel_performance'],
                        'speciales' => [
                            'transmettre_rapport_activite_periodique',
                            'valider_rapport_activite_periodique',
                            'retourner_rapport_activite_periodique',
                            'transmettre_rapport_annuel_performance',
                            'valider_rapport_annuel_performance',
                            'retourner_rapport_annuel_performance',
                        ],
                    ],
                    "Matrice d'arrimage" => [
                        'crud' => [],
                        'speciales' => ['view_matrice_arrimage', 'view_all_matrice_arrimage', 'exporter_matrice_arrimage'],
                    ],
                ],
            ],

            // ════════════════════════════════════════════════════════
            'admin' => [
                'libelle' => 'Administration',
                'icone'   => 'heroicon-o-cog-6-tooth',
                'acces'   => 'access_module_admin',
                'rubriques' => [
                    'Utilisateurs, rôles et journal' => [
                        'crud' => ['user', 'role', 'permission', 'activity'],
                        'speciales' => [],
                    ],
                    'Paramétrage' => [
                        'crud' => ['etat_config', 'parametres_structure', 'parametres_fournisseur'],
                        'speciales' => ['activer_parametres_fournisseur', 'gerer_parametres_execution'],
                    ],
                ],
            ],
        ],

        // Actions générées pour chaque ressource « crud »
        'actions_crud' => ['view_any', 'view', 'create', 'update', 'delete'],
    ],
];
