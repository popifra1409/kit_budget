<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // ✅ Colonnes de taux - TOUTES nullable
            $table->decimal('taux_cnps', 5, 2)->nullable()->change();
            $table->decimal('taux_irnc', 5, 2)->nullable()->change();
            $table->decimal('taux_tva', 5, 2)->nullable()->change();
            $table->decimal('taux_redevance_audiovisuelle', 5, 2)->nullable()->change();
            $table->decimal('taux_feicom', 5, 2)->nullable()->change();

            // ✅ Colonnes de montant - TOUTES nullable
            $table->decimal('montant_cnps', 15, 2)->nullable()->change();
            $table->decimal('montant_irnc', 15, 2)->nullable()->change();
            $table->decimal('montant_tva', 15, 2)->nullable()->change();
            $table->decimal('montant_redevance_audiovisuelle', 15, 2)->nullable()->change();
            $table->decimal('montant_feicom', 15, 2)->nullable()->change();
            $table->decimal('autres_retenues', 15, 2)->nullable()->change();

            // ✅ Dates optionnelles
            $table->date('date_effet')->nullable()->change();
            $table->date('date_fin')->nullable()->change();

            // ✅ Champs texte optionnels
            $table->string('reference_decision')->nullable()->change();
            $table->string('signataire')->nullable()->change();
            $table->text('observations')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne pas remettre NOT NULL - cela peut échouer s'il y a des valeurs NULL
        // Laisser comme nullable même en rollback
    }
};
