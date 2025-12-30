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
        // RÔLE 6 : CONTRÔLEUR FINANCIER
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
        // RÔLE 7 : AGENCE COMPTABLE
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
        $this->command->info('  - super_admin (toutes permissions)');
        $this->command->info('  - operateur_budget');
        $this->command->info('  - chef_service_budget');
        $this->command->info('  - sous_directeur_budget');
        $this->command->info('  - directeur_general');
        $this->command->info('  - controleur_financier');
        $this->command->info('  - agence_comptable');
    }
}