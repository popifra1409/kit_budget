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
        Schema::table('bons_commande', function (Blueprint $table) {
            // Ajouter IR après montant_tva
            $table->decimal('montant_ir', 15, 2)->default(0)->after('montant_tva')
                ->comment('Impôt sur le Revenu (IR)');

            $table->decimal('taux_ir', 5, 2)->default(0)->after('montant_ir')
                ->comment('Taux IR appliqué (%)');

            // Modifier montant_ttc pour inclure IR
            // Formule: TTC = HT + TVA - IR
        });

        Schema::table('lignes_bon_commande', function (Blueprint $table) {
            // Ajouter IR par ligne
            $table->decimal('montant_ir', 15, 2)->default(0)->after('montant_tva')
                ->comment('IR sur cette ligne');

            $table->decimal('taux_ir', 5, 2)->default(0)->after('montant_ir')
                ->comment('Taux IR (%)');

            // Net à payer = HT + TVA - IR
            $table->decimal('net_a_payer', 15, 2)->default(0)->after('montant_ttc')
                ->comment('Net à payer (TTC - IR)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn(['montant_ir', 'taux_ir']);
        });

        Schema::table('lignes_bon_commande', function (Blueprint $table) {
            $table->dropColumn(['montant_ir', 'taux_ir', 'net_a_payer']);
        });
    }
};
    