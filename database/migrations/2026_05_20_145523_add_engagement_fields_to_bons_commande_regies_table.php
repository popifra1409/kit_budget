<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            // ✅ Seulement les 3 colonnes manquantes
            $table->decimal('montant_engage',     15, 2)->default(0)->after('net_a_payer');
            $table->decimal('pourcentage_engage', 5,  2)->default(100)->after('montant_engage');
            $table->decimal('reste_a_engager',    15, 2)->default(0)->after('pourcentage_engage');
        });

        Schema::table('lignes_bons_commande_regies', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_mercuriale_id')->nullable()->after('id');
            $table->string('reference_personnalisee', 100)->nullable()->after('reference_mercuriale_id');

            $table->foreign('reference_mercuriale_id')
                ->references('id')
                ->on('reference_mercuriales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_bons_commande_regies', function (Blueprint $table) {
            $table->dropForeign(['reference_mercuriale_id']);
            $table->dropColumn(['reference_mercuriale_id', 'reference_personnalisee']);
        });

        Schema::table('bons_commande_regies', function (Blueprint $table) {
            $table->dropColumn(['montant_engage', 'pourcentage_engage', 'reste_a_engager']);
        });
    }
};
