<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une RAV apure aussi des achats directs : l'état de compte d'emploi doit donc
 * porter leur n° de facture, comme il porte celui des bons de commande
 * (bon_commande_regies.numero_facture_definitive). Jusqu'ici la référence de
 * l'achat n'était recopiée que dans les observations, en texte libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->string('numero_facture', 100)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->dropColumn('numero_facture');
        });
    }
};
