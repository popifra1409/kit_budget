<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->boolean('mode_arrondi')
                ->default(true)
                ->after('montant_ttc')
                ->comment('true = arrondi à lentier FCFA | false = valeurs décimales telles que saisies');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn('mode_arrondi');
        });
    }
};
