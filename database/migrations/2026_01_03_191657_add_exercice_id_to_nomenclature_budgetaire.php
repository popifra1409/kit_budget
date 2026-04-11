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
            Schema::hasTable('nomenclature_budgetaire') &&
            !Schema::hasColumn('nomenclature_budgetaire', 'exercice_id')
        ) {

            Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
                $table->foreignId('exercice_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('exercices')
                    ->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer les données : utiliser le champ 'exercice' existant
            DB::statement("
                UPDATE nomenclature_budgetaire n
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.annee = n.exercice LIMIT 1
                )
                WHERE n.exercice_id IS NULL AND n.exercice IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nomenclature_budgetaire', 'exercice_id')) {
            Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
                $table->dropForeign(['exercice_id']);
                $table->dropColumn('exercice_id');
            });
        }
    }
};
