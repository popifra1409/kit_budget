<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // ✅ IR standard (distinct de IRNC)
            $table->decimal('taux_ir',    8, 4)->default(0)->after('taux_cnps');
            $table->decimal('montant_ir', 15, 2)->default(0)->after('montant_cnps');

            // ✅ Type IRNC (taux ou forfait) — déjà montant_irnc + taux_irnc
            $table->string('type_irnc')->default('taux')->after('taux_irnc');

            // ✅ Mode forfaitaire uniquement
            $table->decimal('banque',    15, 2)->default(0)->after('autres_retenues');
            $table->decimal('billetage', 15, 2)->default(0)->after('banque');
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropColumn([
                'taux_ir',
                'montant_ir',
                'type_irnc',
                'banque',
                'billetage',
            ]);
        });
    }
};
