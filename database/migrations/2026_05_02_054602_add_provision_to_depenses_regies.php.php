<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->foreignId('provision_ligne_regie_id')
                ->nullable()
                ->after('ligne_regie_avance_id')
                ->constrained('provisions_lignes_regies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->dropForeign(['provision_ligne_regie_id']);
            $table->dropColumn('provision_ligne_regie_id');
        });
    }
};
