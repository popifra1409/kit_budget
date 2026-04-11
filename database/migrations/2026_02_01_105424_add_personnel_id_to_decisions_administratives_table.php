<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Vérifier si la colonne n'existe pas déjà
            if (!Schema::hasColumn('decisions_administratives', 'personnel_id')) {
                $table->foreignId('personnel_id')
                    ->nullable()
                    ->after('budget_id')
                    ->constrained('personnels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            if (Schema::hasColumn('decisions_administratives', 'personnel_id')) {
                $table->dropForeign(['personnel_id']);
                $table->dropColumn('personnel_id');
            }
        });
    }
};
