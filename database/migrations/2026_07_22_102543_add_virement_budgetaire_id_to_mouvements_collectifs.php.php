<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            if (!Schema::hasColumn('mouvements_collectifs', 'virement_budgetaire_id')) {
                $table->unsignedBigInteger('virement_budgetaire_id')
                    ->nullable()
                    ->after('ligne_destination_id')
                    ->comment('VirementBudgetaire lié à ce mouvement de virement');
            }
        });
    }
    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropColumn('virement_budgetaire_id');
        });
    }
};
