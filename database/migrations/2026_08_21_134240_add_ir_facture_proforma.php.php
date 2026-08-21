<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lignes_factures_proforma', function (Blueprint $table) {
            $table->decimal('taux_ir', 5, 2)->default(0)->after('montant_ttc');
            $table->decimal('montant_ir', 15, 2)->default(0)->after('taux_ir');
            $table->decimal('net_a_percevoir', 15, 2)->default(0)->after('montant_ir');
        });

        Schema::table('factures_proforma', function (Blueprint $table) {
            $table->decimal('montant_ir', 15, 2)->default(0)->after('montant_tva');
            $table->decimal('net_a_percevoir', 15, 2)->default(0)->after('montant_ttc');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_factures_proforma', function (Blueprint $table) {
            $table->dropColumn(['taux_ir', 'montant_ir', 'net_a_percevoir']);
        });

        Schema::table('factures_proforma', function (Blueprint $table) {
            $table->dropColumn(['montant_ir', 'net_a_percevoir']);
        });
    }
};
