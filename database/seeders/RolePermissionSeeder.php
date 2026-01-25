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
            'service',
            'tache',
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
        | 3. SUPER ADMIN (TOUT)
        |--------------------------------------------------------------------------
        */
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | 4. ADMIN (GESTION SYSTÈME)
        |--------------------------------------------------------------------------
        */
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | 5. OPÉRATEUR BUDGET
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
        | 6. CHEF SERVICE BUDGET
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
        | 7. SOUS DIRECTEUR BUDGET
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
        | 8. DAAF
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
        | 9. AUTRES RÔLES (LECTURE)
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

        $this->command->info('✅ Rôles et permissions COMPLETS créés avec succès');
    }
}
