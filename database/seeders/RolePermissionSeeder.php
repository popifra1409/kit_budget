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
        ];

        $modulesMarches = [];

        // ── Créer permissions CRUD (firstOrCreate = non destructif) ──
        $this->command->info('📝 Création permissions CRUD...');
        foreach (array_merge($modulesBudget, $modulesComptable, $modulesMarches) as $module) {
            foreach (['view', 'view_any', 'create', 'update', 'delete'] as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$action}_{$module}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // ====================================================
        // 3. PERMISSIONS SPÉCIALES
        // ====================================================
        $special = [
            // Paramètres
            'activer_parametres_fournisseur',
            'blacklister_fournisseur',
            'toggle_fournisseur',
            'activer_budget',
            'adopter_budget',
            'cloturer_budget',
            'activer_service',
            // Exercice
            'ouvrir_exercice',
            'cloturer_exercice',
            'reconduire_exercice',
            // Bon de commande
            'valider_bon_commande',
            'annuler_bon_commande',
            'force_update_bon_commande',
            'engager_bon_commande',
            'desengager_bon_commande',
            'recuperer_bon_commande',
            // Engagement
            'valider_engagement',
            'annuler_engagement',
            'creer_avenant_engagement',
            // Décision Administrative
            'valider_decision_administrative',
            'annuler_decision_administrative',
            'force_update_decision_administrative',
            'engager_decision_administrative',
            'desengager_decision_administrative',
            'recuperer_decision_administrative',
            // Ordonnance
            'emettre_ordonnance_paiement',
            'viser_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'payer_ordonnance_paiement',
            'annuler_ordonnance_paiement',
            'creer_op_depuis_engagement',
            'telecharger_ordonnance_paiement',
            'override_ordonnance_paiement',
            // Bordereau
            'transmettre_bordereau_engagement',
            'receptionner_bordereau_engagement',
            'valider_bordereau_engagement',
            'rejeter_bordereau_engagement',
            'retourner_bordereau_engagement',
            'telecharger_bordereau_engagement',
            'override_bordereau_engagement',
            'gerer_engagements_bordereau',
            // Mémoire dépense
            'valider_memoire_depense',
            'transformer_memoire_depense_en_da',
            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'annuler_transmission',
            'view_all_transmissions',
            'view_my_transmissions',
            // Dossiers
            'cloturer_dossier_fournisseur',
            'annuler_dossier_fournisseur',
            'ajouter_piece_dossier',
            'supprimer_piece_dossier',
            'valider_piece_dossier',
            'invalider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
            'view_my_dossiers',
            // Divers
            'activer_reference_mercuriale',
            'generer_pdf_fiche_controle_engagements',
            // Comptabilité matières
            'soumettre_expression_besoin',
            'valider_expression_besoin',
            'rejeter_expression_besoin',
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
            // Accès modules
            'access_module_portal',
            'access_module_budget',
            'access_module_comptable',
            'access_module_marches',
        ];

        $this->command->info('📝 Création permissions spéciales...');
        foreach ($special as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ====================================================
        // 4. RÔLES ET ATTRIBUTIONS
        // ====================================================
        $this->command->info('🔐 Attribution permissions aux rôles...');

        // ── SUPER ADMIN — absolument tout ────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
        $this->command->line("  ✓ super_admin — " . Permission::count() . " permissions (tout)");

        // ── ADMIN — tout sauf rôles/permissions ──────────────
        // ✅ Admin peut gérer les utilisateurs mais PAS les rôles/permissions
        $exclureAdmin = [
            // Gestion des rôles — réservée super_admin
            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            // Gestion des permissions — réservée super_admin
            'view_permission',
            'view_any_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
            // Suppression utilisateurs — réservée super_admin
            'delete_user',
        ];

        $permissionsAdmin = Permission::whereNotIn('name', $exclureAdmin)
            ->pluck('name')->toArray();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissionsAdmin);
        $this->command->line("  ✓ admin — " . count($permissionsAdmin) . " permissions (sans rôles/permissions)");

        // ── OPÉRATEUR BUDGET ─────────────────────────────────
        $this->syncRolePermissions('operateur_budget', [
            'access_module_portal',
            'access_module_budget',
            'view_budget',
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
        ]);

        // ── CHEF SERVICE BUDGET ──────────────────────────────
        $this->syncRolePermissions('chef_service_budget', [
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
        ]);

        // ── DAAF ─────────────────────────────────────────────
        $this->syncRolePermissions('daaf', [
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
        ]);

        // ── CONTRÔLEUR FINANCIER ─────────────────────────────
        $this->syncRolePermissions('controleur_financier', [
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
        ]);

        // ── DIRECTEUR GÉNÉRAL ────────────────────────────────
        $this->syncRolePermissions('directeur_general', [
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
            'view_any_type_decision',
            'view_type_decision',
            'view_any_personnel',
            'view_personnel',
            // ✅ DG voit les utilisateurs mais PAS les rôles/permissions
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
        ]);

        // ── AGENCE COMPTABLE ─────────────────────────────────
        $this->syncRolePermissions('agence_comptable', [
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
        ]);

        // ── COMPTABLE MATIÈRES ───────────────────────────────
        $this->syncRolePermissions('comptable_matieres', [
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
        ]);

        // ── ORDONNATEUR MATIÈRES ─────────────────────────────
        $this->syncRolePermissions('ordonnateur_matieres', [
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
        ]);

        // ── SERVICE UTILISATEUR ──────────────────────────────
        $this->syncRolePermissions('service_utilisateur', [
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
        ]);

        // ── CHEF SERVICE MARCHÉS ─────────────────────────────
        $this->syncRolePermissions('chef_service_marches', [
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
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->newLine();
        $this->command->info('✅ Rôles et permissions mis à jour avec succès !');
        $this->command->info('📊 Total permissions : ' . Permission::count());
        $this->command->info('👥 Total rôles : ' . Role::count());
        $this->command->newLine();
        $this->command->warn('⚠️  Note importante pour la production :');
        $this->command->line('   • firstOrCreate — aucun rôle/permission supprimé');
        $this->command->line('   • syncPermissions — permissions des rôles mises à jour');
        $this->command->line('   • Permissions custom ajoutées manuellement → à remettre après');
        $this->command->line('   • Admin : accès utilisateurs ✅ | rôles/permissions ❌');
    }

    /**
     * Sync les permissions d'un rôle de façon sécurisée.
     * - firstOrCreate : ne recrée pas un rôle existant
     * - Filtre les permissions inexistantes avec un warning
     * - syncPermissions : remplace la liste du rôle
     */
    protected function syncRolePermissions(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        // ✅ Filtrer les permissions qui n'existent pas encore en base
        $existantes = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
        $manquantes = array_diff($permissions, $existantes);

        if (!empty($manquantes)) {
            $this->command->warn(
                "  ⚠️  [{$roleName}] permissions introuvables : " . implode(', ', $manquantes)
            );
        }

        $role->syncPermissions($existantes);
        $this->command->line("  ✓ {$roleName} — " . count($existantes) . " permissions");
    }
}
