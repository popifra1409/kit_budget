<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bons de commande / marchés : part HT exonérée et part HT taxable, rabais négocié.
 * montant_ht devient le THT APRÈS rabais ; montant_ht_brut conserve le THT avant rabais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->decimal('montant_ht_brut', 18, 2)->nullable();
            $table->decimal('montant_ht_exonere', 18, 2)->default(0);
            $table->decimal('montant_ht_taxable', 18, 2)->default(0);
            $table->decimal('montant_rabais', 18, 2)->default(0);
            $table->string('rabais_sur', 20)->default('exonere');   // exonere | taxable | global
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn(['montant_ht_brut', 'montant_ht_exonere', 'montant_ht_taxable', 'montant_rabais', 'rabais_sur']);
        });
    }
};
