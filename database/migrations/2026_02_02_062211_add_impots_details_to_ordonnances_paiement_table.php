<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            // Ajouter les colonnes de détail des impôts après montant_impot
            $table->decimal('montant_tva', 15, 2)->default(0)->after('montant_impot');
            $table->decimal('montant_ir', 15, 2)->default(0)->after('montant_tva');
            $table->decimal('montant_tsr', 15, 2)->default(0)->after('montant_ir');
            $table->decimal('montant_cnps', 15, 2)->default(0)->after('montant_tsr');
            $table->decimal('montant_irnc', 15, 2)->default(0)->after('montant_cnps');
            $table->decimal('montant_autres_taxes', 15, 2)->default(0)->after('montant_irnc');
        });
    }

    public function down(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->dropColumn([
                'montant_tva',
                'montant_ir',
                'montant_tsr',
                'montant_cnps',
                'montant_irnc',
                'montant_autres_taxes',
            ]);
        });
    }
};
