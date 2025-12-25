<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            // Supprimer les anciennes colonnes de dates
            $table->dropColumn(['date_debut_validite', 'date_fin_validite']);

            // Ajouter les nouvelles colonnes
            $table->date('date_mise_en_vigueur')->nullable()->after('parent_id')
                ->comment('Date de mise en vigueur de cette nomenclature');

            $table->integer('exercice')->nullable()->after('date_mise_en_vigueur')
                ->comment('Année budgétaire / Exercice (ex: 2026)');

            // Index
            $table->index('exercice');
            $table->index(['exercice', 'actif']);
        });

        // Commentaire
        DB::statement("COMMENT ON COLUMN nomenclature_budgetaire.date_mise_en_vigueur IS 'Date de mise en vigueur'");
        DB::statement("COMMENT ON COLUMN nomenclature_budgetaire.exercice IS 'Exercice budgétaire (année)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            // Remettre les anciennes colonnes
            $table->date('date_debut_validite')->nullable();
            $table->date('date_fin_validite')->nullable();

            // Supprimer les nouvelles
            $table->dropColumn(['date_mise_en_vigueur', 'exercice']);
        });
    }
};
