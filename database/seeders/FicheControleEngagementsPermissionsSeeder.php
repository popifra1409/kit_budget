<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FicheControleEngagementsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Réinitialiser le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer la permission principale
        $permission = Permission::firstOrCreate(
            ['name' => 'view_fiche_controle_engagements'],
            ['guard_name' => 'web']
        );

        $this->command->info("✅ Permission 'view_fiche_controle_engagements' créée");

        // Définir les rôles qui peuvent accéder aux fiches
        $rolesAvecAcces = [
            'super_admin',
            'controleur_financier',
            'directeur',
            'chef_service',
            'gestionnaire_budget',
        ];

        // Assigner la permission aux rôles
        foreach ($rolesAvecAcces as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($permission);
                $this->command->info("  ↳ Permission assignée au rôle '{$roleName}'");
            } else {
                $this->command->warn("  ⚠️ Rôle '{$roleName}' non trouvé");
            }
        }

        $this->command->info("\n🎯 Permissions configurées avec succès !");
        $this->command->info("   Les rôles suivants peuvent accéder aux Fiches de Contrôle :");

        foreach ($rolesAvecAcces as $roleName) {
            if (Role::where('name', $roleName)->exists()) {
                $this->command->info("   - {$roleName}");
            }
        }
    }
}
