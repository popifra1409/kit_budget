<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->decimal('net_a_payer', 15, 2)
                ->default(0)
                ->after('montant_autres_taxes');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropColumn('net_a_payer');
        });
    }
};
