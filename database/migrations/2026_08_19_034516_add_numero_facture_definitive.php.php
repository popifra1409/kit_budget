<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            $table->string('numero_facture_definitive', 100)
                ->nullable()
                ->after('numero')
                ->comment('Numéro de la facture définitive physique établie par le fournisseur');

            $table->date('date_facture_definitive')
                ->nullable()
                ->after('numero_facture_definitive');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            $table->dropColumn(['numero_facture_definitive', 'date_facture_definitive']);
        });
    }
};