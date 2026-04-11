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
            // TVA
            $table->enum('type_tva', ['taux', 'forfait'])->default('taux')->after('autres_retenues');
            $table->decimal('taux_tva', 8, 2)->nullable()->after('type_tva')->comment('Taux de TVA en %');
            $table->decimal('montant_tva', 15, 2)->nullable()->after('taux_tva')->comment('Montant TVA forfaitaire');

            // Redevance audiovisuelle
            $table->enum('type_redevance_audiovisuelle', ['taux', 'forfait'])->default('forfait')->after('montant_tva');
            $table->decimal('taux_redevance_audiovisuelle', 8, 2)->nullable()->after('type_redevance_audiovisuelle')->comment('Taux en %');
            $table->decimal('montant_redevance_audiovisuelle', 15, 2)->nullable()->after('taux_redevance_audiovisuelle')->comment('Montant forfaitaire');

            // FECOM CESS
            $table->enum('type_feicom', ['taux', 'forfait'])->default('forfait')->after('montant_redevance_audiovisuelle');
            $table->decimal('taux_feicom', 8, 2)->nullable()->after('type_feicom')->comment('Taux en %');
            $table->decimal('montant_feicom', 15, 2)->nullable()->after('taux_feicom')->comment('Montant forfaitaire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropColumn([
                'type_tva',
                'taux_tva',
                'montant_tva',
                'type_redevance_audiovisuelle',
                'taux_redevance_audiovisuelle',
                'montant_redevance_audiovisuelle',
                'type_feicom',
                'taux_feciom',
                'montant_feicom',
            ]);
        });
    }
};
