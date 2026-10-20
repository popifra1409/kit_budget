<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Permissions créées lors de CETTE exécution (seules à être attribuées aux rôles existants). */
    protected array $nouvelles = [];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ====================================================
        // 1. PERMISSIONS DU CATALOGUE (config/permission.php (clé « catalogue »))
        // ====================================================
        $this->command->info('📝 Création des permissions du catalogue...');
        $catalogue = config('permission.catalogue.modules', []);
        $actions = config('permission.catalogue.actions_crud', ['view_any', 'view', 'create', 'update', 'delete']);

        foreach ($catalogue as $module) {
            $this->creer($module['acces']);
            foreach ($module['rubriques'] as $rubrique) {
                foreach ($rubrique['crud'] ?? [] as $ressource) {
                    foreach ($actions as $action) {
                        $this->creer("{$action}_{$ressource}");
                    }
                }
                foreach ($rubrique['speciales'] ?? [] as $permission) {
                    $this->creer($permission);
                }
            }
        }

        // ====================================================
        // 2. PERMISSIONS UTILISÉES DANS LE CODE MAIS ABSENTES
        // ====================================================
        $this->command->info('🔎 Recherche des permissions utilisées dans le code...');
        $horsCatalogue = [];
        foreach ($this->permissionsDuCode() as $permission) {
            if ($this->creer($permission)) {
                $horsCatalogue[] = $permission;
            }
        }
        if ($horsCatalogue) {
            $this->command->warn('  ⚠️  ' . count($horsCatalogue) . ' permission(s) utilisée(s) dans le code, absentes du catalogue, créées : '
                . implode(', ', $horsCatalogue));
            $this->command->warn('     → à ranger dans config/permission.php (clé « catalogue ») (onglet « Autres » de l\'écran des rôles en attendant).');
        }

        $this->command->line('  ✓ ' . count($this->nouvelles) . ' nouvelle(s) permission(s) créée(s) au total');

        // ====================================================
        // 3. RÔLES ET ATTRIBUTIONS
        // ====================================================
        $this->command->info('🔐 Attribution aux rôles...');
        $this->command->warn('  ⚡ Rôles existants : seules les permissions NOUVELLES sont ajoutées ; '
            . 'les droits retirés dans l\'écran des rôles restent retirés.');

        // ── SUPER ADMIN — reçoit toutes les nouvelles permissions ────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->ajouterPermissionsRole($superAdmin, Permission::pluck('name')->toArray(), 'super_admin');

        // ── ADMIN — tout sauf rôles/permissions ──────────────────────
        $exclureAdmin = [
            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            'view_permission',
            'view_any_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
            'delete_user',
        ];
        $permissionsAdmin = Permission::whereNotIn('name', $exclureAdmin)->pluck('name')->toArray();
        $this->ajouterPermissionsRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']), $permissionsAdmin, 'admin');

        // ── OPÉRATEUR BUDGET ──────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'operateur_budget', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'view_budget',
                'view_budget_programme',
                'saisir_previsions_budget_programme',
                'exporter_budget_programme',
                'gerer_collectif_budgetaire',
                'view_collectif_budgetaire',
                'view_any_collectif_budgetaire',
                'marquer_payee_ordonnance_paiement',
                'view_any_budget',
                'view_prevision_recette',
                'view_any_prevision_recette',
                'create_engagement',
                'view_engagement',
                'view_any_engagement',
                'create_bon_commande',
                'view_bon_commande',
                'view_any_bon_commande',
                'update_bon_commande',
                'view_ordonnance_paiement',
                'view_any_ordonnance_paiement',
                'telecharger_ordonnance_paiement',
                'view_bordereau_engagement',
                'view_any_bordereau_engagement',
                'create_bordereau_engagement',
                'update_bordereau_engagement',
                'gerer_engagements_bordereau',
                'transmettre_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_memoire_depense',
                'view_any_memoire_depense',
                'create_memoire_depense',
                'update_memoire_depense',
                'view_personnel',
                'view_any_personnel',
                'view_any_type_decision',
                'view_type_decision',
                'transmettre_document',
                'view_my_transmissions',
                'view_transmission',
                'view_dossier_fournisseur',
                'view_any_dossier_fournisseur',
                'create_dossier_fournisseur',
                'ajouter_piece_dossier',
                'telecharger_piece_dossier',
                'view_my_dossiers',

                // ✅ AJOUTE : referentiels utilises par les DA — creation et correction,
                //    SANS suppression (un agent ou un service rattache a des documents emis
                //    ne doit pas pouvoir etre efface par un operateur).
                'create_personnel',
                'update_personnel',
                'view_any_service',
                'view_service',
                'create_service',
                'update_service',

                //liquidation et service fait
                'view_any_liquidation',
                'create_liquidation',
            ],
            'operateur_budget'
        );

        // ── CHEF SERVICE BUDGET ───────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'chef_service_budget', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'view_any_recette_reelle',
                'view_recette_reelle',
                'create_recette_reelle',
                'update_recette_reelle',
                'view_any_prevision_recette',
                'update_prevision_recette',
                'view_any_engagement',
                'view_engagement',
                'valider_engagement',
                'view_any_bon_commande',
                'view_bon_commande',
                'update_bon_commande',
                'valider_bon_commande',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'create_ordonnance_paiement',
                'creer_op_depuis_engagement',
                'telecharger_ordonnance_paiement',
                'view_any_bordereau_engagement',
                'view_bordereau_engagement',
                'create_bordereau_engagement',
                'update_bordereau_engagement',
                'gerer_engagements_bordereau',
                'transmettre_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_memoire_depense',
                'view_any_memoire_depense',
                'create_memoire_depense',
                'update_memoire_depense',
                'valider_memoire_depense',
                'devalider_memoire_depense',
                'view_any_type_decision',
                'view_type_decision',
                'view_personnel',
                'view_any_personnel',
                'transmettre_document',
                'retourner_document',
                'cloturer_transmission',
                'view_my_transmissions',
                'view_any_transmission',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'create_dossier_fournisseur',
                'update_dossier_fournisseur',
                'ajouter_piece_dossier',
                'valider_piece_dossier',
                'telecharger_piece_dossier',
                'view_all_dossiers',
                'generer_expression_besoin_bon_commande',

                // ── Budget Programme Triennal ──────────────
                'view_budget_programme',
                'saisir_previsions_budget_programme',
                'exporter_budget_programme',
                'gerer_collectif_budgetaire',
                'valider_collectif_budgetaire',
                'adopter_collectif_budgetaire',
                'view_any_collectif_budgetaire',
                'view_collectif_budgetaire',
                'marquer_payee_ordonnance_paiement',

                // ✅ AJOUTE : referentiels personnel et services (memes droits que l'operateur encadre)
                'create_personnel',
                'update_personnel',
                'view_any_service',
                'view_service',
                'create_service',
                'update_service',

                //liquidation et service fait
                'view_any_liquidation',
                'create_liquidation',

                // ── Exécution budgétaire (liquidation, paiements exceptionnels) ──
                'view_any_paiement_exceptionnel',
                'create_paiement_exceptionnel',
                'regulariser_paiement_exceptionnel',

                'update_virement_budgetaire',
                'view_any_cloture_exercice',
                'gerer_cloture_exercice',
            ],
            'chef_service_budget'
        );

        // ── DAAF ──────────────────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'daaf', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'access_module_comptable',
                'view_any_recette_reelle',
                'view_recette_reelle',
                'create_recette_reelle',
                'update_recette_reelle',
                'view_any_engagement',
                'view_engagement',
                'valider_engagement',
                'view_any_bon_commande',
                'view_bon_commande',
                'valider_bon_commande',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'create_ordonnance_paiement',
                'creer_op_depuis_engagement',
                'emettre_ordonnance_paiement',
                'viser_ordonnance_paiement',
                'valider_ordonnance_paiement',
                'annuler_ordonnance_paiement',
                'telecharger_ordonnance_paiement',
                'view_any_bordereau_engagement',
                'view_bordereau_engagement',
                'create_bordereau_engagement',
                'update_bordereau_engagement',
                'gerer_engagements_bordereau',
                'transmettre_bordereau_engagement',
                'receptionner_bordereau_engagement',
                'valider_bordereau_engagement',
                'rejeter_bordereau_engagement',
                'retourner_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_any_decision_administrative',
                'view_decision_administrative',
                'create_decision_administrative',
                'update_decision_administrative',
                'valider_decision_administrative',
                'view_memoire_depense',
                'view_any_memoire_depense',
                'valider_memoire_depense',
                'devalider_memoire_depense',
                'transformer_memoire_depense_en_da',
                'view_any_type_decision',
                'view_type_decision',
                'view_any_personnel',
                'view_personnel',
                'create_personnel',
                'update_personnel',
                'transmettre_document',
                'retourner_document',
                'cloturer_transmission',
                'view_all_transmissions',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'update_dossier_fournisseur',
                'cloturer_dossier_fournisseur',
                'valider_piece_dossier',
                'telecharger_piece_dossier',
                'view_all_dossiers',
                'view_any_article',
                'view_article',
                'view_any_expression_besoin',
                'view_expression_besoin',
                'valider_expression_besoin',
                'view_any_unite_mesure',
                'view_unite_mesure',
                'view_any_conditionnement',
                'view_conditionnement',
                'view_any_categorie_article',
                'view_categorie_article',
                'view_any_fiche_consolidation_besoin',
                'view_fiche_consolidation_besoin',
                'view_any_reception',
                'view_reception',
                'signer_pv_reception',
                'integrer_reception_stock',
                'view_any_ordre_entree',
                'view_ordre_entree',
                'signer_ordre_entree',
                'transmettre_ordre_entree',
                'view_any_fiche_stock',
                'view_fiche_stock',
                'view_any_bon_sortie_provisoire',
                'view_bon_sortie_provisoire',
                'signer_bsp_ordonnateur',
                'view_any_ordre_sortie',
                'view_ordre_sortie',
                'signer_ordre_sortie',
                'view_any_fiche_detenteur',
                'view_fiche_detenteur',
                'view_any_registre_consommation',
                'view_registre_consommation',
                'view_any_regie_avance',
                'view_regie_avance',
                'create_regie_avance',
                'update_regie_avance',
                'valider_regie_avance',
                'suspendre_regie_avance',
                'cloturer_regie_avance',
                'reapprovisionner_regie_avance',
                'view_any_menu_depense',
                'view_menu_depense',
                'create_menu_depense',
                'update_menu_depense',
                'valider_menu_depense',
                'suspendre_menu_depense',
                'cloturer_menu_depense',
                'reapprovisionner_menu_depense',
                'view_any_decaissement_regie',
                'view_decaissement_regie',
                'create_decaissement_regie',
                'update_decaissement_regie',
                'valider_decaissement_regie',
                'verser_decaissement_regie',
                'apurer_decaissement_regie',
                'view_any_depense_regie',
                'view_depense_regie',
                'valider_depense_regie',
                'annuler_depense_regie',
                'view_any_bon_commande_regie',
                'view_bon_commande_regie',
                'valider_bon_commande_regie',
                'annuler_bon_commande_regie',
                'imprimer_etat_retenues_regie',
                'imprimer_compte_emploi_regie',
                'generer_expression_besoin_bon_commande',

                // ── Budget Programme Triennal ──────────────
                'view_budget_programme',
                'saisir_previsions_budget_programme',
                'exporter_budget_programme',
                'gerer_collectif_budgetaire',
                'valider_collectif_budgetaire',
                'adopter_collectif_budgetaire',
                'annuler_collectif_budgetaire',
                'view_any_collectif_budgetaire',
                'view_collectif_budgetaire',

                // ── Paiement OP ───────────────────────────
                'marquer_payee_ordonnance_paiement',

                // ✅ AJOUTE : services (l'ecran controle desormais strictement view_any_service)
                'view_any_service',
                'view_service',
                'create_service',
                'update_service',

                //liquidation et service fait
                'view_any_liquidation',
                'create_liquidation',
                'liquider_depense',
                'gerer_natures_service_fait',

                // ── Exécution budgétaire (liquidation, paiements exceptionnels) ──
                'view_any_paiement_exceptionnel',
                'create_paiement_exceptionnel',
                'regulariser_paiement_exceptionnel',
                'annuler_execution_mouvement_credit',
                'update_virement_budgetaire',

                'view_any_cloture_exercice',
                'gerer_cloture_exercice',
                'enregistrer_avis_ca',
            ],
            'daaf'
        );

        // ── CONTRÔLEUR FINANCIER ──────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'controleur_financier', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'access_module_marches',
                'view_any_recette_reelle',
                'view_recette_reelle',
                'view_any_engagement',
                'view_engagement',
                'valider_engagement',
                'view_any_bon_commande',
                'view_bon_commande',
                'valider_bon_commande',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'viser_ordonnance_paiement',
                'valider_ordonnance_paiement',
                'telecharger_ordonnance_paiement',
                'view_any_bordereau_engagement',
                'view_bordereau_engagement',
                'receptionner_bordereau_engagement',
                'valider_bordereau_engagement',
                'rejeter_bordereau_engagement',
                'retourner_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_any_decision_administrative',
                'view_decision_administrative',
                'valider_decision_administrative',
                'view_personnel',
                'view_any_personnel',
                'transmettre_document',
                'retourner_document',
                'cloturer_transmission',
                'view_all_transmissions',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'valider_piece_dossier',
                'telecharger_piece_dossier',
                'view_all_dossiers',
                'view_any_regie_avance',
                'view_regie_avance',
                'view_any_menu_depense',
                'view_menu_depense',
                'view_any_decaissement_regie',
                'view_decaissement_regie',
                'view_any_depense_regie',
                'view_depense_regie',
                'valider_depense_regie',
                'view_any_bon_commande_regie',
                'view_bon_commande_regie',
                'valider_bon_commande_regie',
                'imprimer_etat_retenues_regie',
                'imprimer_compte_emploi_regie',

                // Collectifs (lecture)
                'view_any_collectif_budgetaire',
                'view_collectif_budgetaire',

                // ✅ AJOUTE : consultation du journal d'audit (panel Administration, lecture seule)
                'view_any_activity',
                'view_activity',

                'viser_liquidation',
                'view_any_liquidation',

                // ── Exécution budgétaire (liquidation, paiements exceptionnels) ──
                'view_any_paiement_exceptionnel',

                'view_any_cloture_exercice',
            ],
            'controleur_financier'
        );

        // ── DIRECTEUR GÉNÉRAL ─────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'directeur_general', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'view_any_recette_reelle',
                'view_recette_reelle',
                'view_any_engagement',
                'view_engagement',
                'valider_engagement',
                'view_any_bon_commande',
                'view_bon_commande',
                'valider_bon_commande',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'valider_ordonnance_paiement',
                'telecharger_ordonnance_paiement',
                'view_any_bordereau_engagement',
                'view_bordereau_engagement',
                'receptionner_bordereau_engagement',
                'valider_bordereau_engagement',
                'rejeter_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_any_decision_administrative',
                'view_decision_administrative',
                'valider_decision_administrative',
                'view_any_expression_besoin',
                'view_expression_besoin',
                'signer_expression_besoin',
                'view_any_fiche_consolidation_besoin',
                'view_fiche_consolidation_besoin',
                'view_any_type_decision',
                'view_type_decision',
                'view_any_personnel',
                'view_personnel',
                'view_any_user',
                'view_user',
                'transmettre_document',
                'retourner_document',
                'cloturer_transmission',
                'view_all_transmissions',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'cloturer_dossier_fournisseur',
                'valider_piece_dossier',
                'telecharger_piece_dossier',
                'view_all_dossiers',
                'view_any_regie_avance',
                'view_regie_avance',
                'view_any_menu_depense',
                'view_menu_depense',
                'view_any_decaissement_regie',
                'view_decaissement_regie',
                'view_any_depense_regie',
                'view_depense_regie',
                'view_any_bon_commande_regie',
                'view_bon_commande_regie',
                'imprimer_etat_retenues_regie',
                'imprimer_compte_emploi_regie',

                // Collectifs
                'view_any_collectif_budgetaire',
                'view_collectif_budgetaire',
                'gerer_collectif_budgetaire',
                'valider_collectif_budgetaire',
                'adopter_collectif_budgetaire',
                'annuler_collectif_budgetaire',

                // ✅ AJOUTE : pilotage strategique via la matrice d'arrimage
                'access_module_suivi_evaluation',
                'view_matrice_arrimage',
                'view_all_matrice_arrimage',
                'exporter_matrice_arrimage',

                // ✅ AJOUTE : revue des libelles (module Planification)
                'access_module_planification',
                'view_arborescence_libelles',
                'view_all_arborescence_libelles',
                'exporter_arborescence_libelles',

                // ✅ AJOUTE : consultation du journal d'audit (panel Administration, lecture seule)
                'view_any_activity',
                'view_activity',

                'liquider_depense',

                // ── Exécution budgétaire (liquidation, paiements exceptionnels) ──
                'view_any_liquidation',
                'view_any_paiement_exceptionnel',
                'autoriser_paiement_exceptionnel',
                'annuler_execution_mouvement_credit',

                'view_any_cloture_exercice',
                'arreter_reports_credits',
            ],
            'directeur_general'
        );

        // ── AGENCE COMPTABLE ──────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'agence_comptable', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'access_module_comptable',
                'view_any_recette_reelle',
                'view_recette_reelle',
                'view_any_engagement',
                'view_engagement',
                'view_any_bon_commande',
                'view_bon_commande',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'payer_ordonnance_paiement',
                'telecharger_ordonnance_paiement',
                'view_any_bordereau_engagement',
                'view_bordereau_engagement',
                'telecharger_bordereau_engagement',
                'view_any_decision_administrative',
                'view_decision_administrative',
                'view_personnel',
                'view_any_personnel',
                'view_my_transmissions',
                'cloturer_transmission',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'ajouter_piece_dossier',
                'telecharger_piece_dossier',
                'view_my_dossiers',
                'view_any_article',
                'view_article',
                'view_any_stock',
                'view_stock',
                'view_any_fiche_stock',
                'view_fiche_stock',
                'view_any_expression_besoin',
                'view_expression_besoin',
                'view_any_fiche_consolidation_besoin',
                'view_fiche_consolidation_besoin',
                'view_any_reception',
                'view_reception',
                'view_any_ordre_entree',
                'view_ordre_entree',
                'view_any_bon_sortie_fourniture',
                'view_bon_sortie_fourniture',
                'view_any_bon_sortie_provisoire',
                'view_bon_sortie_provisoire',
                'view_any_ordre_sortie',
                'view_ordre_sortie',
                'view_any_fiche_detenteur',
                'view_fiche_detenteur',
                'view_any_registre_consommation',
                'view_registre_consommation',
                'view_any_regie_avance',
                'view_regie_avance',
                'view_any_menu_depense',
                'view_menu_depense',
                'view_any_decaissement_regie',
                'view_decaissement_regie',
                'create_decaissement_regie',
                'update_decaissement_regie',
                'valider_decaissement_regie',
                'verser_decaissement_regie',
                'apurer_decaissement_regie',
                'view_any_depense_regie',
                'view_depense_regie',
                'view_any_bon_commande_regie',
                'view_bon_commande_regie',
                'imprimer_etat_retenues_regie',
                'imprimer_compte_emploi_regie',

                // Collectifs (lecture)
                'view_any_collectif_budgetaire',
                'view_collectif_budgetaire',

                'view_any_liquidation',

                // ── Exécution budgétaire (liquidation, paiements exceptionnels) ──
                'view_any_paiement_exceptionnel',
                'payer_paiement_exceptionnel',
                'view_any_cloture_exercice',
            ],
            'agence_comptable'
        );

        // ── RESPONSABLE RÉGIE ─────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'responsable_regie', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_budget',
                'view_any_regie_avance',
                'view_regie_avance',
                'view_any_menu_depense',
                'view_menu_depense',
                'view_any_decaissement_regie',
                'view_decaissement_regie',
                'create_decaissement_regie',
                'view_any_depense_regie',
                'view_depense_regie',
                'create_depense_regie',
                'update_depense_regie',
                'delete_depense_regie',
                'view_any_bon_commande_regie',
                'view_bon_commande_regie',
                'create_bon_commande_regie',
                'update_bon_commande_regie',
                'delete_bon_commande_regie',
                'imprimer_etat_retenues_regie',
                'imprimer_compte_emploi_regie',
                'view_any_fournisseur',
                'view_fournisseur',
            ],
            'responsable_regie'
        );

        // ── COMPTABLE MATIÈRES ────────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'comptable_matieres', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_comptable',
                'view_any_article',
                'view_article',
                'create_article',
                'update_article',
                'view_any_stock',
                'view_stock',
                'ajuster_stock',
                'inventorier_stock',
                'view_any_fiche_stock',
                'view_fiche_stock',
                'view_any_expression_besoin',
                'view_expression_besoin',
                'create_expression_besoin',
                'update_expression_besoin',
                'valider_expression_besoin',
                'consolider_expression_besoin',
                'view_any_fiche_consolidation_besoin',
                'view_fiche_consolidation_besoin',
                'update_fiche_consolidation_besoin',
                'view_any_unite_mesure',
                'view_unite_mesure',
                'create_unite_mesure',
                'update_unite_mesure',
                'view_any_conditionnement',
                'view_conditionnement',
                'create_conditionnement',
                'update_conditionnement',
                'view_any_categorie_article',
                'view_categorie_article',
                'create_categorie_article',
                'update_categorie_article',
                'view_any_reception',
                'view_reception',
                'create_reception',
                'update_reception',
                'signer_pv_reception',
                'integrer_reception_stock',
                'view_any_ordre_entree',
                'view_ordre_entree',
                'update_ordre_entree',
                'signer_ordre_entree',
                'transmettre_ordre_entree',
                'view_any_bon_sortie_fourniture',
                'view_bon_sortie_fourniture',
                'view_any_bon_sortie_provisoire',
                'view_bon_sortie_provisoire',
                'create_bon_sortie_provisoire',
                'update_bon_sortie_provisoire',
                'signer_bsp_comptable',
                'executer_bon_sortie_provisoire',
                'view_any_ordre_sortie',
                'view_ordre_sortie',
                'create_ordre_sortie',
                'update_ordre_sortie',
                'signer_ordre_sortie',
                'view_any_fiche_detenteur',
                'view_fiche_detenteur',
                'create_fiche_detenteur',
                'update_fiche_detenteur',
                'retourner_fiche_detenteur',
                'view_any_registre_consommation',
                'view_registre_consommation',
                'generer_bon_commande_expression_besoin',
                //liquidation et service fait
                'view_any_liquidation',
                'create_liquidation',
                'certifier_service_fait',
            ],
            'comptable_matieres'
        );

        // ── ORDONNATEUR MATIÈRES ──────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'ordonnateur_matieres', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_comptable',
                'view_any_article',
                'view_article',
                'view_any_expression_besoin',
                'view_expression_besoin',
                'valider_expression_besoin',
                'view_any_reception',
                'view_reception',
                'signer_pv_reception',
                'integrer_reception_stock',
                'view_any_ordre_entree',
                'view_ordre_entree',
                'signer_ordre_entree',
                'transmettre_ordre_entree',
                'view_any_fiche_stock',
                'view_fiche_stock',
                'view_any_bon_sortie_provisoire',
                'view_bon_sortie_provisoire',
                'signer_bsp_ordonnateur',
                'view_any_ordre_sortie',
                'view_ordre_sortie',
                'signer_ordre_sortie',
                'view_any_fiche_detenteur',
                'view_fiche_detenteur',
                'view_any_registre_consommation',
                'view_registre_consommation',
            ],
            'ordonnateur_matieres'
        );

        // ── SERVICE UTILISATEUR ───────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'service_utilisateur', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_comptable',
                'view_any_bon_sortie_fourniture',
                'view_bon_sortie_fourniture',
                'create_bon_sortie_fourniture',
                'update_bon_sortie_fourniture',
                'soumettre_bon_sortie_fourniture',
                'view_any_bon_sortie_provisoire',
                'view_bon_sortie_provisoire',
                'signer_bsp_demandeur',
                'view_any_registre_consommation',
                'view_registre_consommation',
            ],
            'service_utilisateur'
        );

        // ── CHEF SERVICE MARCHÉS ──────────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'chef_service_marches', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_marches',
                'view_any_fournisseur',
                'view_fournisseur',
                'view_any_dossier_fournisseur',
                'view_dossier_fournisseur',
                'create_dossier_fournisseur',
                'update_dossier_fournisseur',
                'ajouter_piece_dossier',
                'valider_piece_dossier',
                'telecharger_piece_dossier',
                'view_all_dossiers',
                'view_any_bon_commande',
                'view_bon_commande',
                'view_any_engagement',
                'view_engagement',
                'view_any_ordonnance_paiement',
                'view_ordonnance_paiement',
                'view_personnel',
                'view_any_personnel',
                'transmettre_document',
                'retourner_document',
                'cloturer_transmission',
                'view_my_transmissions',
            ],
            'chef_service_marches'
        );

        // ── RESPONSABLE PLANIFICATION STRATÉGIQUE ─────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'responsable_planification', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_planification',

                // Cadre Strategique de Performance (CSP)
                'view_any_csp_ministere_sante',
                'view_csp_ministere_sante',
                'create_csp_ministere_sante',
                'update_csp_ministere_sante',

                // Plan Strategique de Performance (PSP)
                'view_any_plan_strategique_ep',
                'view_plan_strategique_ep',
                'create_plan_strategique_ep',
                'update_plan_strategique_ep',
                'transmettre_plan_strategique_ep',
                'valider_plan_strategique_ep',
                'retourner_plan_strategique_ep',
                'cloturer_plan_strategique_ep',

                // Sous-Programme strategique
                'view_any_sous_programme_ep',
                'view_sous_programme_ep',
                'create_sous_programme_ep',
                'update_sous_programme_ep',
                'transmettre_sous_programme_ep',
                'valider_sous_programme_ep',
                'retourner_sous_programme_ep',

                // Action (classification budgetaire reutilisee, module Budget)
                'view_any_action',
                'view_action',
                'create_action',
                'update_action',

                // Activite (classification budgetaire reutilisee, + workflow)
                'view_any_activite',
                'view_activite',
                'create_activite',
                'update_activite',
                'transmettre_activite',
                'valider_activite',
                'retourner_activite',

                // Tache (costing, classification budgetaire reutilisee)
                'view_any_tache',
                'view_tache',
                'create_tache',
                'update_tache',

                // Indicateurs
                'view_any_indicateur',
                'view_indicateur',
                'create_indicateur',
                'update_indicateur',
                'saisir_valeur_indicateur',
                'valider_valeur_indicateur',

                // Extrants
                'view_any_extrant',
                'view_extrant',
                'create_extrant',
                'update_extrant',

                // Transmission generique
                'transmettre_document',
                'view_my_transmissions',

                // ✅ AJOUTE : acces a la matrice d'arrimage (raccourci depuis la Planification)
                'access_module_suivi_evaluation',
                'view_matrice_arrimage',
                'view_all_matrice_arrimage',
                'exporter_matrice_arrimage',

                // ✅ AJOUTE : revue des libelles
                'view_arborescence_libelles',
                'view_all_arborescence_libelles',
                'exporter_arborescence_libelles',
            ],
            'responsable_planification'
        );

        // ── RESPONSABLE PROGRAMMATION ──────────────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'responsable_programmation', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_programmation',

                // PPA (Programme de Performance Annuel)
                'view_any_ppa_exercice',
                'view_ppa_exercice',
                'create_ppa_exercice',
                'update_ppa_exercice',
                'transmettre_ppa_exercice',
                'valider_ppa_exercice',
                'retourner_ppa_exercice',

                // CBMT (Cadrage Budgetaire a Moyen Terme)
                'view_any_cbmt_exercice',
                'view_cbmt_exercice',
                'create_cbmt_exercice',
                'update_cbmt_exercice',
                'transmettre_cbmt_exercice',
                'valider_cbmt_exercice',
                'retourner_cbmt_exercice',

                // CDMT (Cadre de Depenses a Moyen Terme)
                'view_any_cdmt_exercice',
                'view_cdmt_exercice',
                'create_cdmt_exercice',
                'update_cdmt_exercice',
                'transmettre_cdmt_exercice',
                'valider_cdmt_exercice',
                'retourner_cdmt_exercice',

                // Lecture de la Planification pour construire le PPA/CDMT
                'view_any_plan_strategique_ep',
                'view_plan_strategique_ep',
                'view_any_sous_programme_ep',
                'view_sous_programme_ep',
                'view_any_action',
                'view_action',
                'view_any_activite',
                'view_activite',
                'view_any_tache',
                'view_tache',
                'view_any_indicateur',
                'view_indicateur',
                'view_any_extrant',
                'view_extrant',

                // ✅ AJOUTE : revue des libelles (lecture, acces via le module Planification)
                'access_module_planification',
                'view_arborescence_libelles',
                'view_all_arborescence_libelles',
                'exporter_arborescence_libelles',

                'transmettre_document',
                'view_my_transmissions',
            ],
            'responsable_programmation'
        );

        // ── RESPONSABLE SUIVI ET EVALUATION ───────────────────────────
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'responsable_suivi_evaluation', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_suivi_evaluation',

                // Lecture de la Planification/Programmation pour construire les rapports
                'view_any_plan_strategique_ep',
                'view_plan_strategique_ep',
                'view_any_sous_programme_ep',
                'view_sous_programme_ep',
                'view_any_activite',
                'view_activite',
                'view_any_tache',
                'view_tache',
                'view_any_indicateur',
                'view_indicateur',
                'view_any_extrant',
                'view_extrant',
                'view_any_ppa_exercice',
                'view_ppa_exercice',

                // Rapport d'activite periodique (Annexe 9)
                'view_any_rapport_activite_periodique',
                'view_rapport_activite_periodique',
                'create_rapport_activite_periodique',
                'update_rapport_activite_periodique',
                'transmettre_rapport_activite_periodique',
                'valider_rapport_activite_periodique',
                'retourner_rapport_activite_periodique',

                // RAP - Rapport Annuel de Performance (Annexe 10)
                'view_any_rapport_annuel_performance',
                'view_rapport_annuel_performance',
                'create_rapport_annuel_performance',
                'update_rapport_annuel_performance',
                'transmettre_rapport_annuel_performance',
                'valider_rapport_annuel_performance',
                'retourner_rapport_annuel_performance',

                // ✅ AJOUTE : Matrice d'arrimage strategique (vision complete)
                'view_matrice_arrimage',
                'view_all_matrice_arrimage',
                'exporter_matrice_arrimage',

                // ✅ AJOUTE : revue des libelles (acces via le module Planification)
                'access_module_planification',
                'view_arborescence_libelles',
                'view_all_arborescence_libelles',
                'exporter_arborescence_libelles',

                'transmettre_document',
                'view_my_transmissions',
            ],
            'responsable_suivi_evaluation'
        );

        // ── ✅ NOUVEAU : RESPONSABLE DE SOUS-PROGRAMME ─────────────────
        // Pas de 'view_all_matrice_arrimage' : ne voit QUE les sous-programmes
        // dont il est responsable (filtre applique cote serveur par le service).
        $this->ajouterPermissionsRole(
            Role::firstOrCreate(['name' => 'responsable_sous_programme', 'guard_name' => 'web']),
            [
                'access_module_portal',
                'access_module_suivi_evaluation',
                'view_matrice_arrimage',
                'exporter_matrice_arrimage',
                'view_any_rapport_activite_periodique',
                'view_rapport_activite_periodique',

                // Revue des libelles de SES sous-programmes uniquement (pas de view_all_*)
                'access_module_planification',
                'view_arborescence_libelles',
                'exporter_arborescence_libelles',
            ],
            'responsable_sous_programme'
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->newLine();
        $this->command->info('✅ Permissions mises à jour avec succès (mode additif) !');
        $this->command->info('📊 Total permissions en base : ' . Permission::count());
        $this->command->info('👥 Total rôles en base : '       . Role::count());
        $this->command->newLine();
        $this->command->line('  ℹ️  Aucune permission existante n\'a été supprimée.');
        $this->command->line('  ℹ️  Seules les nouvelles permissions ont été ajoutées aux rôles.');
    }

    // ============================================================
    // HELPER — Mode additif (ne supprime aucune permission existante)
    // ============================================================

    /**
     * Ajoute uniquement les NOUVELLES permissions à un rôle.
     * Les permissions déjà attribuées au rôle sont conservées intactes.
     *
     * Utilise givePermissionTo() (additif) plutot que syncPermissions() (destructif).
     *
     * @param Role   $role        Le rôle à mettre à jour
     * @param array  $permissions Liste des permissions voulues pour ce rôle
     * @param string $label       Nom pour les logs console
     */
    /** Crée la permission si elle n'existe pas ; retourne true si elle vient d'être créée. */
    protected function creer(string $nom): bool
    {
        $permission = Permission::firstOrCreate(['name' => $nom, 'guard_name' => 'web']);
        if ($permission->wasRecentlyCreated) {
            $this->nouvelles[] = $nom;
            return true;
        }

        return false;
    }

    /**
     * Permissions vérifiées dans le code : ->can('x'), ->cannot('x'), hasPermissionTo('x'),
     * hasAnyPermission(['x', …]), @can('x'). Les capacités de policy à un mot (« update »…) sont ignorées.
     */
    protected function permissionsDuCode(): array
    {
        $trouvees = [];
        $dossiers = array_filter([app_path(), resource_path('views'), base_path('routes')], 'is_dir');

        foreach ($dossiers as $dossier) {
            $fichiers = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS));
            foreach ($fichiers as $fichier) {
                if (!str_ends_with($fichier->getFilename(), '.php')) {
                    continue;
                }
                $code = file_get_contents($fichier->getPathname());

                preg_match_all("/(?:->can|->cannot|hasPermissionTo|@can|@cannot)\\(\\s*['\"]([a-z][a-z0-9]*(?:_[a-z0-9]+)+)['\"]\\s*[,)]/", $code, $m);
                array_push($trouvees, ...$m[1]);

                preg_match_all("/hasAnyPermission\\(\\s*\\[([^\\]]*)\\]/", $code, $listes);
                foreach ($listes[1] as $liste) {
                    preg_match_all("/['\"]([a-z][a-z0-9]*(?:_[a-z0-9]+)+)['\"]/", $liste, $noms);
                    array_push($trouvees, ...$noms[1]);
                }
            }
        }

        return array_values(array_unique($trouvees));
    }

    protected function ajouterPermissionsRole(Role $role, array $permissions, string $label): void
    {
        // Permissions existantes en base parmi la liste fournie
        $existantesEnBase = Permission::whereIn('name', $permissions)
            ->pluck('name')
            ->toArray();

        // Avertir si des permissions du tableau n'existent pas encore en base
        $manquantesEnBase = array_diff($permissions, $existantesEnBase);
        if (!empty($manquantesEnBase)) {
            $this->command->warn(
                "  ⚠️  [{$label}] permissions introuvables en base : "
                    . implode(', ', $manquantesEnBase)
            );
        }

        // Permissions déjà attribuées à ce rôle
        $dejAttribuees = $role->permissions()->pluck('name')->toArray();

        // ✅ Rôle EXISTANT : seules les permissions créées lors de cette exécution lui sont proposées.
        //    Une permission retirée volontairement dans l'écran des rôles n'est donc jamais rendue.
        //    Rôle NOUVEAU (ou super_admin) : liste par défaut complète.
        if (!$role->wasRecentlyCreated && $label !== 'super_admin') {
            $existantesEnBase = array_values(array_intersect($existantesEnBase, $this->nouvelles));
        }

        // Nouvelles permissions à ajouter (différence entre voulu et existant)
        $aAjouter = array_values(array_diff($existantesEnBase, $dejAttribuees));

        if (!empty($aAjouter)) {
            $role->givePermissionTo($aAjouter);
            $this->command->line(
                "  ✓ {$label} — +" . count($aAjouter) . " nouvelle(s) permission(s) "
                    . "(total rôle : " . (count($dejAttribuees) + count($aAjouter)) . ")"
            );
        } else {
            $this->command->line(
                "  ✓ {$label} — aucune nouvelle permission (total : " . count($dejAttribuees) . ")"
            );
        }
    }
}
