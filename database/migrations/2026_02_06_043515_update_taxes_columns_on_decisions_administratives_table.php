<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {

            // ❌ Suppression de l’ancienne colonne IR
            if (Schema::hasColumn('decisions_administratives', 'ir')) {
                $table->dropColumn('ir');
            }
            if (Schema::hasColumn('decisions_administratives', 'cnps')) {
                $table->dropColumn('cnps');
            }

            // ✅ Taux
            $table->decimal('taux_cnps', 5, 2)
                ->default(4.20)
                ->after('montant_brut');

            $table->decimal('taux_irnc', 5, 2)
                ->nullable()
                ->after('taux_cnps');

            // ✅ Montants calculés
            $table->decimal('montant_cnps', 15, 2)
                ->nullable()
                ->after('taux_irnc');

            $table->decimal('montant_irnc', 15, 2)
                ->nullable()
                ->after('montant_cnps');

            // ✅ Total des taxes
            $table->decimal('total_taxes', 15, 2)
                ->nullable()
                ->after('autres_retenues');
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {

            // 🔙 Restaurer l’ancienne colonne IR
            $table->decimal('ir', 15, 2)
                ->nullable()
                ->after('cnps');

            // 🔙 Supprimer les nouvelles colonnes
            $table->dropColumn([
                'taux_cnps',
                'taux_irnc',
                'montant_cnps',
                'montant_irnc',
                'total_taxes',
            ]);
        });
    }
};
