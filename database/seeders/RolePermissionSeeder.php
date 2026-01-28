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
            'dossier_fournisseur',      // ← AJOUTÉ
            'engagement',
            'etat_config',
            'exercice',
            'fournisseur',
            'memoire_depense',
            'nomenclature_budgetaire',
            'parametres_fournisseur',
            'parametres_structure',
            'permission',
            'piece_dossier',            // ← AJOUTÉ
            'prevision_recette',
            'programme',
            'recette_reelle',
            'reference_mercuriale',
            'role',
            'service',
            'tache',
            'transmission',
            'user',
            'virement_budgetaire',
        ];

        /*
        |--------------------------------------------------------------------------
        | 2. CRÉATION DES PERMISSIONS CRUD
        |--------------------------------------------------------------------------
        */
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
        | 3. PERMISSIONS SPÉCIALES (non CRUD)
        |--------------------------------------------------------------------------
        */
        $specialPermissions = [
            // Paramètres & Structure
            'activer_parametres_fournisseur',
            'blacklister_fournisseur',
            'activer_budget',
            'adopter_budget',
            'cloturer_budget',
            'activer_service',

            // Exercice
            'ouvrir_exercice',
            'cloturer_exercice',
            'reconduire_exercice',

            // Engagement
            'valider_engagement',
            'annuler_engagement',

            // Décision Administrative
            'valider_decision_administrative',
            'annuler_decision_administrative',

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

            // Dossiers Fournisseurs ← NOUVEAUX
            'cloturer_dossier_fournisseur',
            'annuler_dossier_fournisseur',
            'ajouter_piece_dossier',
            'supprimer_piece_dossier',
            'valider_piece_dossier',
            'invalider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',  // Voir tous les dossiers (admin)
            'view_my_dossiers',   // Voir uniquement ses dossiers

            // Références mercuriales
            'activer_reference_mercuriale',
        ];

        foreach ($specialPermissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. SUPER ADMIN (TOUT)
        |--------------------------------------------------------------------------
        */
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | 5. ADMIN (GESTION SYSTÈME)
        |--------------------------------------------------------------------------
        */
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | 6. OPÉRATEUR BUDGET
        |--------------------------------------------------------------------------
        */
        $operateur = Role::firstOrCreate(['name' => 'operateur_budget']);
        $operateur->syncPermissions([
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
        | 7. CHEF SERVICE BUDGET
        |--------------------------------------------------------------------------
        */
        $chefService = Role::firstOrCreate(['name' => 'chef_service_budget']);
        $chefService->syncPermissions([
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
        | 8. SOUS DIRECTEUR BUDGET
        |--------------------------------------------------------------------------
        */
        $sousDirecteur = Role::firstOrCreate(['name' => 'sous_directeur_budget']);
        $sousDirecteur->syncPermissions([
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

            // Workflow
            'transmettre_document',
            'retourner_document',
            'cloturer_transmission',
            'view_my_transmissions',
            'view_any_transmission',

            // Dossiers fournisseurs
            'view_any_dossier_fournisseur',
            'view_dossier_fournisseur',
            'update_dossier_fournisseur',
            'valider_piece_dossier',
            'telecharger_piece_dossier',
            'view_all_dossiers',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9. DAAF
        |--------------------------------------------------------------------------
        */
        $daaf = Role::firstOrCreate(['name' => 'daaf']);
        $daaf->syncPermissions([
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',
            'valider_engagement',

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
        | 10. CONTROLEUR FINANCIER
        |--------------------------------------------------------------------------
        */
        $controleur = Role::firstOrCreate(['name' => 'controleur_financier']);
        $controleur->syncPermissions([
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
        | 11. DIRECTEUR GENERAL
        |--------------------------------------------------------------------------
        */
        $directeur = Role::firstOrCreate(['name' => 'directeur_general']);
        $directeur->syncPermissions([
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
        | 12. AGENCE COMPTABLE
        |--------------------------------------------------------------------------
        */
        $agence = Role::firstOrCreate(['name' => 'agence_comptable']);
        $agence->syncPermissions([
            // Recettes
            'view_any_recette_reelle',
            'view_recette_reelle',

            // Engagements
            'view_any_engagement',
            'view_engagement',

            // Bons de commande
            'view_any_bon_commande',
            'view_bon_commande',

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

        $this->command->info('✅ Rôles et permissions COMPLETS créés avec workflow, transmissions et dossiers fournisseurs.');
    }
}
