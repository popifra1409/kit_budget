<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Ajouter la colonne nomenclature_commune_id
            $table->foreignId('nomenclature_commune_id')
                ->nullable()
                ->after('budget_id')
                ->constrained('nomenclature_budgetaire')
                ->onDelete('restrict')
                ->comment('Nomenclature budgétaire principale utilisée pour ce BC');

            // Index pour les recherches
            $table->index('nomenclature_commune_id');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropForeign(['nomenclature_commune_id']);
            $table->dropIndex(['nomenclature_commune_id']);
            $table->dropColumn('nomenclature_commune_id');
        });
    }
};
