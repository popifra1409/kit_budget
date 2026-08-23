<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('decaissements_regies', function (Blueprint $table) {
            $table->foreignId('decision_administrative_id')->nullable()
                ->after('regie_avance_id')
                ->constrained('decisions_administratives')
                ->nullOnDelete()
                ->comment('DA source ayant financé cette tranche (réapprovisionnement)');
        });
    }

    public function down(): void
    {
        Schema::table('decaissements_regies', function (Blueprint $table) {
            $table->dropForeign(['decision_administrative_id']);
            $table->dropColumn('decision_administrative_id');
        });
    }
};
