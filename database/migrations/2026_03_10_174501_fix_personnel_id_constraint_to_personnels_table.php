<?php

/**
 * ========================================================================
 * MIGRATION : Corriger personnel_id pour pointer vers personnels
 * ========================================================================
 * 
 * PROBLÈME :
 * - personnel_id pointe vers users (ancien système) ❌
 * - Doit pointer vers personnels (nouveau système) ✅
 * 
 * COMMANDE :
 * php artisan make:migration fix_personnel_id_constraint_to_personnels_table
 * 
 * Puis copier ce code dans le fichier créé
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Supprimer l'ancienne contrainte (users)
            $table->dropForeign(['personnel_id']);

            // Créer la nouvelle contrainte (personnels)
            $table->foreign('personnel_id')
                ->references('id')
                ->on('personnels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropForeign(['personnel_id']);

            // Restaurer l'ancienne contrainte (users)
            $table->foreign('personnel_id')
                ->references('id')
                ->on('users');
        });
    }
};
