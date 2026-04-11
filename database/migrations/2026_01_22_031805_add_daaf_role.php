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
        // RÔLE : DAAF (Directeur des Affaires Administratives et Financières)
        // ========================================

        $daaf = Role::firstOrCreate([
            'name' => 'daaf',
            'guard_name' => 'web'
        ]);

        // Le DAAF a un rôle stratégique de validation et de supervision
        // Il se situe entre le Directeur Général et les services opérationnels
        // Il a des permissions étendues de validation et de contrôle

        $daaf->givePermissionTo([
            // Consultation
            'view_bordereau',
            'view_any_bordereau',
            'view_bordereaux_attente',
            'view_all_bordereaux',

            // Actions de validation
            'transmettre_bordereau',
            'valider_bordereau',
            'rejeter_bordereau',
            'retourner_bordereau',

            // Actions spécifiques DAAF
            'receptionner_bordereau',  // Peut réceptionner des bordereaux
            'valider_engagement',       // Valide les engagements
            'rejeter_engagement',       // Peut rejeter les engagements

            // Statistiques et suivi
            'view_stats_workflow',
        ]);

        // Message de confirmation
        echo "✅ Rôle DAAF créé avec succès !\n";
        echo "   Permissions accordées : 13 permissions\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer le rôle DAAF
        $daaf = Role::where('name', 'daaf')->first();

        if ($daaf) {
            $daaf->delete();
            echo "✅ Rôle DAAF supprimé\n";
        }

        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
