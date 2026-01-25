<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ========================================
        // PERMISSIONS UTILISATEURS
        // ========================================

        // Créer les permissions utilisateurs si elles n'existent pas
        $userPermissions = [
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'restore_user',
            'force_delete_user',
        ];

        foreach ($userPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ========================================
        // RÔLE : ADMIN (Administrateur Délégué)
        // ========================================

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);

        // Récupérer TOUTES les permissions SAUF celles interdites
        $allPermissions = Permission::all();

        // Permissions INTERDITES pour admin
        $forbiddenPermissions = [
            // Journal d'activité
            'view_activity',
            'view_any_activity',
            'create_activity',
            'update_activity',
            'delete_activity',
            'restore_activity',
            'force_delete_activity',

            // Rôles
            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            'restore_role',
            'force_delete_role',

            // Permissions
            'view_permission',
            'view_any_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
            'restore_permission',
            'force_delete_permission',

            // Shield (gestion permissions Filament)
            'view_shield::role',
            'view_any_shield::role',
            'create_shield::role',
            'update_shield::role',
            'delete_shield::role',
        ];

        // Filtrer les permissions autorisées
        $allowedPermissions = $allPermissions->filter(function ($permission) use ($forbiddenPermissions) {
            return !in_array($permission->name, $forbiddenPermissions);
        });

        // Assigner toutes les permissions autorisées
        $admin->syncPermissions($allowedPermissions);

        // Ajouter explicitement les permissions utilisateurs
        $admin->givePermissionTo([
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
        ]);

        echo "✅ Rôle Admin créé avec succès !\n";
        echo "   Permissions accordées : " . $admin->permissions->count() . " permissions\n";
        echo "   Permissions interdites : " . count($forbiddenPermissions) . " permissions\n";
        echo "\n";
        echo "   ❌ Interdit : Journal d'activité, Rôles, Permissions\n";
        echo "   ✅ Autorisé : Tout le reste (sauf modification super_admin)\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer le rôle admin
        $admin = Role::where('name', 'admin')->first();

        if ($admin) {
            $admin->delete();
            echo "✅ Rôle Admin supprimé\n";
        }

        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
