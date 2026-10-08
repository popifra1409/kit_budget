<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Données de correction d'un avenant (taxes, montant brut, objet…) : nécessaires pour
 * recalculer les OP et conserver l'historique. Sans effet si la colonne existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('avenants', 'donnees_correction')) {
            Schema::table('avenants', function (Blueprint $table) {
                $table->json('donnees_correction')->nullable();
            });
        }

        if (!Schema::hasColumn('avenants', 'corriger_ordonnances')) {
            Schema::table('avenants', function (Blueprint $table) {
                $table->boolean('corriger_ordonnances')->default(true);
            });
        }
    }

    public function down(): void
    {
        // Colonnes conservées : elles portent l'historique des avenants.
    }
};
