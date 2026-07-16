<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ====================================================
        // 1. MODULES CRUD — Budget
        // ====================================================
        $modulesBudget = [
            'action',
            'activite',
            'activity',
            'bon_commande',
            'bordereau_engagement',
            'budget',
            'decision_administrative',
            'dossier_fournisseur',
            'engagement',
            'etat_config',
            'exercice',
            'fournisseur',
            'memoire_depense',
            'nomenclature_budgetaire',
            'ordonnance_paiement',
            'parametres_fournisseur',
            'parametres_structure',
            'permission',
            'personnel',
            'piece_dossier',
            'prevision_recette',
            'programme',
            'recette_reelle',
            'reference_mercuriale',
            'regime_fiscal',
            'role',
            'service',
            'tache',
            'transmission',
            'type_decision',
            'type_engagement',
            'user',
            'virement_budgetaire',
            'fiche_controle_engagements',
            'avenant_engagement',
            'regie_avance',
            'menu_depense',
            'decaissement_regie',
            'depense_regie',
            'bon_commande_regie',
        ];

        // ====================================================
        // 2. MODULES CRUD — Comptabilité Matières
        // ====================================================
        $modulesComptable = [
            'article',
            'stock',
            'fiche_stock',
            'expression_besoin',
            'reception',
            'ordre_entree',
            'bon_sortie_fourniture',
            'bon_sortie_provisoire',
            'ordre_sortie',
            'fiche_detenteur',
            'registre_consommation',
            'unite_mesure',
            'conditionnement',
            'categorie_article',
            'fiche_consolidation_besoin',
        ];

        $modulesMarches = [];

        // ── Créer permissions CRUD (firstOrCreate = non destructif) ──
        $this->command->info('📝 Création permissions CRUD...');
        $permsCrudCreees = 0;
        foreach (array_merge($modulesBudget, $modulesComptable, $modulesMarches) as $module) {
            foreach (['view', 'view_any', 'create', 'update', 'delete'] as $action) {
                $created = Permission::firstOrCreate([
                    'name'       => "{$action}_{$module}",
                    'guard_name' => 'web',
                ]);
                if ($created->wasRecentlyCreated) $permsCrudCreees++;
            }
        }
        $this->command->line("  ✓ {$permsCrudCreees} nouvelle(s) permission(s) CRUD créée(s)");

        // ====================================================
        // 3. PERMISSIONS SPÉCIALES
        // ====================================================
        $special = [
            'activer_parametres_fournisseur',
            'blacklister_fournisseur',
            'toggle_fournisseur',
            'activer_budget',
            'adopter_budget',
            'cloturer_budget',
            'activer_service',
            'ouvrir_exercice',
            'cloturer_exercice',
            'reconduire_exercice',
            'valider_bon_commande',
            'annuler_bon_commande',
            'force_update_bon_commande',
            'engager_bon_commande',
            'desengager_bon_commande',
            'recuperer_bon_commande',
            'valider_engagement',
            'annuler_engagement',
            'creer_avenant_engagement',
            'valider_decision_administrative',
            'annuler_decision_administrative',
            'force_update_decision_administrative',
            'engager_decision_administrative',
            'desengager_decision_administrative',
            'recuperer_decision_administrative',
            'emettre_ordonnance_paiement',
            'viser_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'payer_ordonnance_paiement',
            'annuler_ordonnance_paiement',
            'creer_op_depuis_engagement',
            'telecharger_ordonnance_paiement',
            'override_ordonnance_paiement',
            'transmettre_bordereau_engagement',
            'receptionner_bordereau_engagement',
            'valider_bordereau_engagement',
            'rejeter_bordereau_engagement',
            'retourner_bordereau_engagement',
            'telecharger_bordereau_engagement',
            'override_bordereau_engagement',
            'gerer_engagements_bordereau',
            'valider_memoire_depense',
            'devalider_memoire_depense',
            'transformer_memoire_depense_en_da',
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'annuler_transmission',
            'view_all_transmissions',
            'view_my_transmissions',
            'cloturer_dossier_fournisseur',
            'annuler_dossier_fournisseur',
            'ajouter_piece_dossier',
            'supprimer_piece_dossier',
            'valider_piece_dossier',
            'invalider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
            'view_my_dossiers',
            'activer_reference_mercuriale',
            'generer_pdf_fiche_controle_engagements',
            'soumettre_expression_besoin',
            'valider_expression_besoin',
            'rejeter_expression_besoin',
            'signer_expression_besoin',
            'consolider_expression_besoin',
            'signer_pv_reception',
            'integrer_reception_stock',
            'signer_ordre_entree',
            'transmettre_ordre_entree',
            'soumettre_bon_sortie_fourniture',
            'signer_bsp_demandeur',
            'signer_bsp_comptable',
            'signer_bsp_ordonnateur',
            'executer_bon_sortie_provisoire',
            'signer_ordre_sortie',
            'transmettre_ordre_sortie',
            'retourner_fiche_detenteur',
            'ajuster_stock',
            'inventorier_stock',
            'access_module_portal',
            'access_module_budget',
            'access_module_comptable',
            'access_module_marches',
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
            'generer_bon_commande_expression_besoin',
            'generer_expression_besoin_bon_commande',

            // ── Budget Programme Triennal ──────────────────
            'view_budget_programme',
            'saisir_previsions_budget_programme',
            'exporter_budget_programme',
            'gerer_collectif_budgetaire',
            'valider_collectif_budgetaire',

            // ── Marquer payée OP ──────────────────────────
            'marquer_payee_ordonnance_paiement',
        ];

        $this->command->info('📝 Création permissions spéciales...');
        $permsSpecialesCreees = 0;
        foreach ($special as $perm) {
            $created = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if ($created->wasRecentlyCreated) $permsSpecialesCreees++;
        }
        $this->command->line("  ✓ {$permsSpecialesCreees} nouvelle(s) permission(s) spéciale(s) créée(s)");

        // ====================================================
        // 4. RÔLES ET ATTRIBUTIONS
        // ====================================================
        $this->command->info('🔐 Attribution des nouvelles permissions aux rôles...');
        $this->command->warn('  ⚡ Mode additif — aucune permission existante supprimée');

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
                'valider_collectif_budgetaire',
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
                'marquer_payee_ordonnance_paiement',
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

                // ── Paiement OP ───────────────────────────
                'marquer_payee_ordonnance_paiement',
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
     * ✅ Remplace syncPermissions() (qui était destructif)
     *    par givePermissionTo() (qui est additif)
     *
     * @param Role   $role        Le rôle à mettre à jour
     * @param array  $permissions Liste des permissions voulues pour ce rôle
     * @param string $label       Nom pour les logs console
     */
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
