<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {
            // Suppression de la contrainte unique classique
            $table->dropUnique('uniq_prevision_nomenclature');
        });

        // Création d’un index unique PARTIEL (PostgreSQL)
        DB::statement("
            CREATE UNIQUE INDEX uniq_prevision_nomenclature_active
            ON lignes_previsions_recettes (prevision_recette_id, nomenclature_id)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        // Suppression de l’index partiel
        DB::statement("
            DROP INDEX IF EXISTS uniq_prevision_nomenclature_active
        ");

        // Restauration de la contrainte unique classique
        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {
            $table->unique(
                ['prevision_recette_id', 'nomenclature_id'],
                'uniq_prevision_nomenclature'
            );
        });
    }
};
