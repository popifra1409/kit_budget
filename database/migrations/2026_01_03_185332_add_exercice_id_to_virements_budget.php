<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('virements_budget') &&
            !Schema::hasColumn('virements_budget', 'exercice_id')
        ) {

            Schema::table('virements_budget', function (Blueprint $table) {
                $table->foreignId('exercice_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('exercices')
                    ->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer les données existantes vers l'exercice actif
            DB::statement("
                UPDATE virements_budget v
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.actif = true LIMIT 1
                )
                WHERE v.exercice_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('virements_budget', 'exercice_id')) {
            Schema::table('virements_budget', function (Blueprint $table) {
                $table->dropForeign(['exercice_id']);
                $table->dropColumn('exercice_id');
            });
        }
    }
};
