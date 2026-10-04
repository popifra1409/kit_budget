<?php
// database/migrations/2026_10_04_000001_add_titre_to_nomenclature_budgetaire.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titre de la prévision à moyen terme (CBMT) porté par chaque ligne de nomenclature :
 *  - dépenses  : 1 dette, 2 personnel, 3 biens et services, 4 transferts, 5 investissement, 6 autres ;
 *  - ressources: 1 fiscales affectées, 2 domaine et services, 3 dotations et subventions, 4 autres.
 * Rempli automatiquement d'après le code (config/cbmt.php), modifiable ligne par ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            $table->unsignedTinyInteger('titre')->nullable()->after('groupe_id');
            $table->index(['type', 'titre']);
        });
    }

    public function down(): void
    {
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            $table->dropIndex(['type', 'titre']);
            $table->dropColumn('titre');
        });
    }
};
