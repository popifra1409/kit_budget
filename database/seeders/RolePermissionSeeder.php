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

        /*
        |--------------------------------------------------------------------------
        | 1. DÉFINITION DES MODULES
        |--------------------------------------------------------------------------
        */
        $modules = [
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
        ];

        /*
        |--------------------------------------------------------------------------
        | 2. CRÉATION DES PERMISSIONS CRUD
        |--------------------------------------------------------------------------
        */
        $this->command->info('📝 Création des permissions CRUD...');

        foreach ($modules as $module) {
            foreach (['view', 'view_any', 'create', 'update', 'delete'] as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action}_{$module}",
                    'guard_name' => 'web',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. PERMISSIONS SPÉCIALES
        |--------------------------------------------------------------------------
        */
        $specialPermissions = [
            // Paramètres & Structure
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

            // Décision Administrative
            'valider_decision_administrative',
            'annuler_decision_administrative',
            'force_update_decision_administrative',
            'engager_decision_administrative',
            'desengager_decision_administrative',
            'recuperer_decision_administrative',

            // ========================================
            // ORDONNANCE DE PAIEMENT - COMPLET
            // ========================================
            'emettre_ordonnance_paiement',
            'viser_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'payer_ordonnance_paiement',
            'annuler_ordonnance_paiement',
            'creer_op_depuis_engagement',
            'telecharger_ordonnance_paiement',
            'override_ordonnance_paiement',

            // ========================================
            // BORDEREAU D'ENGAGEMENT - COMPLET
            // ========================================
            'transmettre_bordereau_engagement',
            'receptionner_bordereau_engagement',
            'valider_bordereau_engagement',
            'rejeter_bordereau_engagement',
            'retourner_bordereau_engagement',
            'telecharger_bordereau_engagement',
            'override_bordereau_engagement',
            'gerer_engagements_bordereau',

            // Mémoire de dépense
            'valider_memoire_depense',
            'publier_memoire_depense',

            // Workflow / Transmissions
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'annuler_transmission',
            'view_all_transmissions',
            'view_my_transmissions',

            // Dossiers Fournisseurs
            'cloturer_dossier_fournisseur',
            'annuler_dossier_fournisseur',
            'ajouter_piece_dossier',
            'supprimer_piece_dossier',
            'valider_piece_dossier',
            'invalider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
            'view_my_dossiers',

            // Références mercuriales
            'activer_reference_mercuriale',

            //controle et suivi des engagements
            'generer_pdf_fiche_controle_engagements',
        ];

        $this->command->info('📝 Création des permissions spéciales...');

        foreach ($specialPermissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3b. PERMISSIONS D'ACCÈS AUX MODULES
        |--------------------------------------------------------------------------
        */
        $this->command->info('📝 Création des permissions modules...');

        $modulePermissions = [
            'access_module_budget',
            'access_module_comptable',
            'access_module_marches',
            'access_module_portal',  // accès au portail (tous)
        ];

        foreach ($modulePermissions as $perm) {
            Permission::firstOrCreate([
                'name'       => $perm,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. ATTRIBUTION DES PERMISSIONS AUX RÔLES
        |--------------------------------------------------------------------------
        */
        $this->command->info('🔐 Attribution des permissions aux rôles...');

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMIN (TOUT)
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Super Admin');
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | ADMIN (GESTION SYSTÈME)
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Admin');
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | ACCÈS MODULES PAR RÔLE
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Attribution accès modules aux rôles');

        // Tous les rôles → accès portail
        $rolesPortail = [
            'super_admin',
            'admin',
            'operateur_budget',
            'chef_service_budget',
            'daaf',
            'controleur_financier',
            'directeur_general',
            'agence_comptable'
        ];

        foreach ($rolesPortail as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo('access_module_portal');
        }

        // Module Budget → tous les rôles métier
        $rolesBudget = [
            'super_admin',
            'admin',
            'operateur_budget',
            'chef_service_budget',
            'daaf',
            'controleur_financier',
            'directeur_general',
            'agence_comptable'
        ];

        foreach ($rolesBudget as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo('access_module_budget');
        }

        // Module Comptable → admin + daaf + agence_comptable
        $rolesComptable = ['super_admin', 'admin', 'daaf', 'agence_comptable'];

        foreach ($rolesComptable as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo('access_module_comptable');
        }

        // Module Marchés → admin + daaf + controleur_financier
        $rolesMarches = ['super_admin', 'admin', 'daaf', 'controleur_financier'];

        foreach ($rolesMarches as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo('access_module_marches');
        }

        /*
        |--------------------------------------------------------------------------
        | OPÉRATEUR BUDGET
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Opérateur Budget');
        $this->syncRolePermissions('operateur_budget', [
            // Budget
            'view_budget',
            'view_any_budget',
            'view_prevision_recette',
            'view_any_prevision_recette',

            // Engagement
            'create_engagement',
            'view_engagement',
            'view_any_engagement',

            // Bon de commande
            'create_bon_commande',
            'view_bon_commande',
            'view_any_bon_commande',
            'update_bon_commande',

            // Ordonnance de paiement
            'view_ordonnance_paiement',
            'view_any_ordonnance_paiement',
            'telecharger_ordonnance_paiement',

            // Bordereau
            'view_bordereau_engagement',
            'view_any_bordereau_engagement',
            'create_bordereau_engagement',
            'update_bordereau_engagement',
            'gerer_engagements_bordereau',
            'transmettre_bordereau_engagement',
            'telecharger_bordereau_engagement',

            // Personnel
            'view_personnel',
            'view_any_personnel',

            // Type decision
            'view_any_type_decision',
            'view_type_decision',

            // Workflow
            'transmettre_document',
            'view_my_transmissions',
            'view_transmission',

            // Dossiers fournisseurs
            'view_dossier_fournisseur',
            'view_any_dossier_fournisseur',
            'create_dossier_fournisseur',
            'ajouter_piece_dossier',
            'telecharger_piece_dossier',
            'view_my_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CHEF SERVICE BUDGET
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Chef Service Budget');
        $this->syncRolePermissions('chef_service_budget', [
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',
            'view_any_prevision_recette',
            'update_prevision_recette',

            // Engagements
            'view_any_engagement',
            'view_engagement',
            'valider_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',
            'update_bon_commande',
            'valider_bon_commande',

            // Ordonnance de paiement
            'view_any_ordonnance_paiement',
            'view_ordonnance_paiement',
            'create_ordonnance_paiement',
            'creer_op_depuis_engagement',
            'telecharger_ordonnance_paiement',

            // Bordereau
            'view_any_bordereau_engagement',
            'view_bordereau_engagement',
            'create_bordereau_engagement',
            'update_bordereau_engagement',
            'gerer_engagements_bordereau',
            'transmettre_bordereau_engagement',
            'telecharger_bordereau_engagement',

            // Type decision
            'view_any_type_decision',
            'view_type_decision',

            // Personnel
            'view_personnel',
            'view_any_personnel',

            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'view_my_transmissions',
            'view_any_transmission',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'create_dossier_fournisseur',
            'update_dossier_fournisseur',
            'ajouter_piece_dossier',
            'valider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | DAAF
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → DAAF');
        $this->syncRolePermissions('daaf', [
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',
            'valider_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',
            'valider_bon_commande',

            // Ordonnance de paiement - COMPLET
            'view_any_ordonnance_paiement',
            'view_ordonnance_paiement',
            'create_ordonnance_paiement',
            'creer_op_depuis_engagement',
            'emettre_ordonnance_paiement',
            'viser_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'annuler_ordonnance_paiement',
            'telecharger_ordonnance_paiement',

            // Bordereau - COMPLET
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

            // Décisions administratives
            'view_any_decision_administrative',
            'view_decision_administrative',
            'create_decision_administrative',
            'update_decision_administrative',
            'valider_decision_administrative',

            // Type decision
            'view_any_type_decision',
            'view_type_decision',

            // Personnel
            'view_any_personnel',
            'view_personnel',
            'create_personnel',
            'update_personnel',

            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'view_all_transmissions',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'update_dossier_fournisseur',
            'cloturer_dossier_fournisseur',
            'valider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CONTROLEUR FINANCIER
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Contrôleur Financier');
        $this->syncRolePermissions('controleur_financier', [
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',
            'valider_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',
            'valider_bon_commande',

            // Ordonnance de paiement
            'view_any_ordonnance_paiement',
            'view_ordonnance_paiement',
            'viser_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'telecharger_ordonnance_paiement',

            // Bordereau
            'view_any_bordereau_engagement',
            'view_bordereau_engagement',
            'receptionner_bordereau_engagement',
            'valider_bordereau_engagement',
            'rejeter_bordereau_engagement',
            'retourner_bordereau_engagement',
            'telecharger_bordereau_engagement',

            // Décisions administratives
            'view_any_decision_administrative',
            'view_decision_administrative',
            'valider_decision_administrative',

            // Personnel
            'view_personnel',
            'view_any_personnel',

            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'view_all_transmissions',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'valider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | DIRECTEUR GENERAL
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Directeur Général');
        $this->syncRolePermissions('directeur_general', [
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',
            'valider_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',
            'valider_bon_commande',

            // Ordonnance de paiement
            'view_any_ordonnance_paiement',
            'view_ordonnance_paiement',
            'valider_ordonnance_paiement',
            'telecharger_ordonnance_paiement',

            // Bordereau
            'view_any_bordereau_engagement',
            'view_bordereau_engagement',
            'receptionner_bordereau_engagement',
            'valider_bordereau_engagement',
            'rejeter_bordereau_engagement',
            'telecharger_bordereau_engagement',

            // Décisions administratives
            'view_any_decision_administrative',
            'view_decision_administrative',
            'valider_decision_administrative',

            // Type decision
            'view_any_type_decision',
            'view_type_decision',

            // Personnel
            'view_any_personnel',
            'view_personnel',
            'view_any_role',
            'view_role',

            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'view_all_transmissions',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'cloturer_dossier_fournisseur',
            'valider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | AGENCE COMPTABLE
        |--------------------------------------------------------------------------
        */
        $this->command->info('  → Agence Comptable');
        $this->syncRolePermissions('agence_comptable', [
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',

            // Ordonnance de paiement
            'view_any_ordonnance_paiement',
            'view_ordonnance_paiement',
            'payer_ordonnance_paiement',
            'telecharger_ordonnance_paiement',

            // Bordereau
            'view_any_bordereau_engagement',
            'view_bordereau_engagement',
            'telecharger_bordereau_engagement',

            // Décisions administratives
            'view_any_decision_administrative',
            'view_decision_administrative',

            // Personnel
            'view_personnel',
            'view_any_personnel',

            // Workflow
            'view_my_transmissions',
            'cloturer_transmission',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'ajouter_piece_dossier',
            'telecharger_piece_dossier',
            'view_my_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | NETTOYAGE CACHE
        |--------------------------------------------------------------------------
        */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('');
        $this->command->info('✅ Rôles et permissions mis à jour avec succès !');
        $this->command->info('📊 Total permissions : ' . Permission::count());
        $this->command->info('👥 Total rôles : ' . Role::count());
    }

    /**
     * Helper pour synchroniser les permissions d'un rôle
     */
    protected function syncRolePermissions(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }
}
