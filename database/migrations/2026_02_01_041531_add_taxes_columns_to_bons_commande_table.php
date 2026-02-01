<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Nouvelles taxes
            $table->decimal('montant_tsr', 15, 2)->default(0)->after('montant_ir')
                ->comment('Taxe Statistique Régionale (produits importés)');

            $table->decimal('montant_cnps', 15, 2)->default(0)->after('montant_tsr')
                ->comment('CNPS (pour décisions)');

            $table->decimal('montant_irnc', 15, 2)->default(0)->after('montant_cnps')
                ->comment('IR Non Commercial');

            $table->decimal('montant_autres_taxes', 15, 2)->default(0)->after('montant_irnc')
                ->comment('Autres taxes éventuelles');

            // Indicateur produit importé
            $table->boolean('produit_importe')->default(false)->after('montant_autres_taxes');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn([
                'montant_tsr',
                'montant_cnps',
                'montant_irnc',
                'montant_autres_taxes',
                'produit_importe',
            ]);
        });
    }
};
