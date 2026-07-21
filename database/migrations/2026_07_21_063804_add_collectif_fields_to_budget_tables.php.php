<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Lignes de dépenses (table existante)
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->decimal('montant_initial', 15, 2)->default(0)->after('budget_rectifie');
            $table->boolean('est_issue_collectif')->default(false);
            $table->foreignId('collectif_creation_id')->nullable()->constrained('collectifs_budgetaires')->nullOnDelete();
        });

        // Lignes de recettes (nom correct : lignes_previsions_recettes)
        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {
            $table->decimal('montant_initial', 15, 2)->default(0)->after('montant_rectifie');
            $table->boolean('est_issue_collectif')->default(false);
            $table->foreignId('collectif_creation_id')->nullable()->constrained('collectifs_budgetaires')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->dropColumn(['montant_initial', 'est_issue_collectif', 'collectif_creation_id']);
        });

        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {
            $table->dropColumn(['montant_initial', 'est_issue_collectif', 'collectif_creation_id']);
        });
    }
};
