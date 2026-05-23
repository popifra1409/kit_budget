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
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            $table->boolean('exonere_tva')->default(true)->after('net_a_payer');
            $table->boolean('exonere_ir')->default(true)->after('exonere_tva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            //
        });
    }
};
