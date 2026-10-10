<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le mouvement collectif de mode « créer une nouvelle nomenclature » insertisait une
 * nomenclature sans laisser de trace du lien : rien ne permettait de la retirer quand
 * le mouvement était supprimé, et le code se retrouvait dupliqué dans les sélecteurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->foreignId('nomenclature_creee_id')
                ->nullable()
                ->after('nouvelle_ligne_recette_id')
                ->constrained('nomenclature_budgetaire')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nomenclature_creee_id');
        });
    }
};
