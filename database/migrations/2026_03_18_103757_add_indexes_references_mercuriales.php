<?php

// ============================================
// MIGRATION - Index pour Optimisation Recherche
// Créer avec : php artisan make:migration add_indexes_to_references_mercuriales_table
// ============================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reference_mercuriales', function (Blueprint $table) {
            // ✅ Index composite sur exercice_id et actif (filtre principal)
            $table->index(['exercice_id', 'actif'], 'idx_exercice_actif');

            // ✅ Index sur code_reference pour recherche rapide
            $table->index('code_reference', 'idx_code_reference');

            // ✅ Index sur rubrique pour filtrage
            $table->index('rubrique', 'idx_rubrique');

            // ✅ Index FULLTEXT sur designation et rubrique pour recherche textuelle rapide
            // Note : Fonctionne avec MySQL/MariaDB
            if (config('database.default') === 'mysql') {
                DB::statement('CREATE FULLTEXT INDEX idx_fulltext_search ON reference_mercuriales(designation, rubrique)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('references_mercuriales', function (Blueprint $table) {
            $table->dropIndex('idx_exercice_actif');
            $table->dropIndex('idx_code_reference');
            $table->dropIndex('idx_rubrique');

            // Drop FULLTEXT index
            if (config('database.default') === 'mysql') {
                DB::statement('DROP INDEX idx_fulltext_search ON references_mercuriales');
            }
        });
    }
};
