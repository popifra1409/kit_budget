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
            'engagement',
            'etat_config',
            'exercice',
            'fournisseur',
            'memoire_depense',
            'nomenclature_budgetaire',
            'parametres_fournisseur',
            'parametres_structure',
            'permission',
            'prevision_recette',
            'programme',
            'recette_reelle',
            'role',
            'service',
            'tache',
            'user',
            'virement_budgetaire',
            'reference_mercuriale'
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
            'activer_parametres_fournisseur',
            'blacklister_fournisseur',
            'activer_budget',
            'adopter_budget',
            'cloturer_budget',
            'ouvrir_exercice',
            'cloturer_exercice',
            'reconduire_exercice',
            'valider_engagement',
            'annuler_engagement',
            'valider_decision_administrative',
            'annuler_decision_administrative',
            'valider_memoire_depense',
            'publier_memoire_depense',
            'activer_service',
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
            'view_budget',
            'view_any_budget',
            'create_engagement',
            'view_prevision_recette',
            'view_any_prevision_recette',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7. CHEF SERVICE BUDGET
        |--------------------------------------------------------------------------
        */
        $chefService = Role::firstOrCreate(['name' => 'chef_service_budget']);
        $chefService->syncPermissions([
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',
            'view_any_prevision_recette',
            'update_prevision_recette',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 8. SOUS DIRECTEUR BUDGET
        |--------------------------------------------------------------------------
        */
        $sousDirecteur = Role::firstOrCreate(['name' => 'sous_directeur_budget']);
        $sousDirecteur->syncPermissions([
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9. DAAF
        |--------------------------------------------------------------------------
        */
        $daaf = Role::firstOrCreate(['name' => 'daaf']);
        $daaf->syncPermissions([
            'view_any_recette_reelle',
            'view_recette_reelle',
            'create_recette_reelle',
            'update_recette_reelle',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 10. AUTRES RÔLES (LECTURE)
        |--------------------------------------------------------------------------
        */
        foreach (
            [
                'controleur_financier',
                'directeur_general',
                'agence_comptable',
            ] as $roleName
        ) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions([
                'view_any_recette_reelle',
                'view_recette_reelle',
            ]);
        }

        $this->command->info('✅ Rôles et permissions COMPLETS créés avec toutes les actions spéciales.');
    }
}
