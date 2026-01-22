<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ========================================
        // PERMISSIONS BORDEREAUX D'ENGAGEMENT
        // ========================================

        $permissions = [
            // Gestion des bordereaux
            'view_bordereau',
            'view_any_bordereau',
            'create_bordereau',
            'update_bordereau',
            'delete_bordereau',

            // Actions workflow
            'soumettre_bordereau',
            'transmettre_bordereau',
            'receptionner_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',
            'cloturer_bordereau',

            // Gestion des engagements
            'valider_engagement',
            'rejeter_engagement',

            // Vues spécifiques
            'view_bordereaux_attente',
            'view_bordereaux_service',
            'view_all_bordereaux',

            // Statistiques
            'view_stats_workflow',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ========================================
        // RÔLE 1 : SUPER ADMIN
        // ========================================

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // ========================================
        // RÔLE 2 : OPÉRATEUR BUDGET
        // ========================================

        $operateur = Role::firstOrCreate([
            'name' => 'operateur_budget',
            'guard_name' => 'web'
        ]);

        $operateur->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'create_bordereau',
            'update_bordereau',
            'soumettre_bordereau',
            'view_bordereaux_service',
        ]);

        // ========================================
        // RÔLE 3 : CHEF SERVICE BUDGET
        // ========================================

        $chefService = Role::firstOrCreate([
            'name' => 'chef_service_budget',
            'guard_name' => 'web'
        ]);

        $chefService->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_service',
            'view_bordereaux_attente',
            'transmettre_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',
            'view_stats_workflow',
        ]);

        // ========================================
        // RÔLE 4 : SOUS-DIRECTEUR BUDGET
        // ========================================

        $sousDirecteur = Role::firstOrCreate([
            'name' => 'sous_directeur_budget',
            'guard_name' => 'web'
        ]);

        $sousDirecteur->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',
            'transmettre_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',
            'view_stats_workflow',
        ]);

        // ========================================
        // RÔLE 5 : DIRECTEUR GÉNÉRAL
        // ========================================

        $directeurGeneral = Role::firstOrCreate([
            'name' => 'directeur_general',
            'guard_name' => 'web'
        ]);

        $directeurGeneral->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',
            'transmettre_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',
            'view_stats_workflow',
        ]);

        // ========================================
        // RÔLE 6 : DAAF (Directeur des Affaires Administratives et Financières)
        // Position hiérarchique : Entre DG et services opérationnels
        // Rôle : Validation stratégique et supervision financière
        // ========================================

        $daaf = Role::firstOrCreate([
            'name' => 'daaf',
            'guard_name' => 'web'
        ]);

        $daaf->givePermissionTo([
            // Consultation étendue
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',

            // Actions de validation (similaire DG mais avec réception)
            'transmettre_bordereau',
            'receptionner_bordereau',      // ← Spécifique DAAF
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',

            // Validation des engagements (rôle stratégique)
            'valider_engagement',          // ← Spécifique DAAF
            'rejeter_engagement',          // ← Spécifique DAAF

            // Statistiques et pilotage
            'view_stats_workflow',
        ]);

        // ========================================
        // RÔLE 7 : CONTRÔLEUR FINANCIER
        // ========================================

        $controleur = Role::firstOrCreate([
            'name' => 'controleur_financier',
            'guard_name' => 'web'
        ]);

        $controleur->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',
            'receptionner_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',
            'transmettre_bordereau',
            'valider_engagement',
            'rejeter_engagement',
            'view_stats_workflow',
        ]);

        // ========================================
        // RÔLE 8 : AGENCE COMPTABLE
        // ========================================

        $agenceComptable = Role::firstOrCreate([
            'name' => 'agence_comptable',
            'guard_name' => 'web'
        ]);

        $agenceComptable->givePermissionTo([
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',
            'receptionner_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'cloturer_bordereau',
            'view_stats_workflow',
        ]);

        $this->command->info('✅ Rôles et permissions créés avec succès !');
        $this->command->info('');
        $this->command->info('Rôles créés :');
        $this->command->info('  1. super_admin (toutes permissions)');
        $this->command->info('  2. operateur_budget (6 permissions)');
        $this->command->info('  3. chef_service_budget (9 permissions)');
        $this->command->info('  4. sous_directeur_budget (9 permissions)');
        $this->command->info('  5. directeur_general (9 permissions)');
        $this->command->info('  6. daaf (13 permissions) ← NOUVEAU');
        $this->command->info('  7. controleur_financier (12 permissions)');
        $this->command->info('  8. agence_comptable (9 permissions)');
    }
}
