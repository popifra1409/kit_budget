<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque recette porte DEUX montants :
 *  - montant_constate : recette ATTENDUE (créance constatée : facture, prise en charge, subvention notifiée) ;
 *  - montant          : recette ENCAISSÉE (seule comptée dans le recouvré).
 * RAR = montant_constate − montant, calculé.
 *
 * Reprise des données : recettes encaissées → attendu = encaissé (RAR nul) ;
 * recettes saisies en « constatée » → attendu = montant saisi, encaissé = 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recettes_reelles', function (Blueprint $table) {
            $table->decimal('montant_constate', 18, 2)->nullable()->after('montant');
        });

        DB::table('recettes_reelles')->where('statut', 'constatee')
            ->update(['montant_constate' => DB::raw('montant'), 'montant' => 0]);

        DB::table('recettes_reelles')->whereNull('montant_constate')
            ->update(['montant_constate' => DB::raw('montant')]);
    }

    public function down(): void
    {
        DB::table('recettes_reelles')->where('statut', 'constatee')
            ->update(['montant' => DB::raw('montant_constate')]);

        Schema::table('recettes_reelles', function (Blueprint $table) {
            $table->dropColumn('montant_constate');
        });
    }
};
