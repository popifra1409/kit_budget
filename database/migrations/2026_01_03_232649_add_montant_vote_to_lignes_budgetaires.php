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
            Schema::hasTable('lignes_budgetaires') &&
            !Schema::hasColumn('lignes_budgetaires', 'montant_vote')
        ) {

            Schema::table('lignes_budgetaires', function (Blueprint $table) {
                $table->decimal('montant_vote', 15, 2)->default(0)->after('montant_initial');
            });

            // Copier montant_initial dans montant_vote pour les données existantes
            DB::statement("
                UPDATE lignes_budgetaires 
                SET montant_vote = COALESCE(montant_initial, 0)
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lignes_budgetaires', 'montant_vote')) {
            Schema::table('lignes_budgetaires', function (Blueprint $table) {
                $table->dropColumn('montant_vote');
            });
        }
    }
};
