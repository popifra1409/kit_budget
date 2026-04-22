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
        Schema::table('avenants', function (Blueprint $table) {
            $table->decimal('montant_taxes_original', 15, 2)->default(0)->after('delta_montant');
            $table->decimal('montant_taxes_corrige', 15, 2)->default(0)->after('montant_taxes_original');
            $table->boolean('corriger_ordonnances')->default(true)->after('montant_taxes_corrige');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avenants', function (Blueprint $table) {
            //
        });
    }
};
