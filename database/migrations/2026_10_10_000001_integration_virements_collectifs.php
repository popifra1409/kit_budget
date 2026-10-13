<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mouvement de collectif issu de l'INTÉGRATION d'un mouvement de crédits de gestion déjà existant :
 * il n'est jamais réexécuté ni contre-passé par le collectif (le virement existe indépendamment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->boolean('integre_hors_collectif')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropColumn('integre_hors_collectif');
        });
    }
};
