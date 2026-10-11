<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne manquante : le modèle RegieAvance écrit montant_engage (recalculs, décaissements),
 * mais la colonne n'existait pas → engagé vide sur la fiche et disponible figé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('regies_avances', 'montant_engage')) {
            Schema::table('regies_avances', function (Blueprint $table) {
                $table->decimal('montant_engage', 18, 2)->default(0)->after('montant_depense');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('regies_avances', 'montant_engage')) {
            Schema::table('regies_avances', function (Blueprint $table) {
                $table->dropColumn('montant_engage');
            });
        }
    }
};
