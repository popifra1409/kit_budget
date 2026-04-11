<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->foreignId('regime_fiscal_id')
                ->nullable()
                ->after('statut')
                ->constrained('regimes_fiscaux')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropForeign(['regime_fiscal_id']);
            $table->dropColumn('regime_fiscal_id');
        });
    }
};
