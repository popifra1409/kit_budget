<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDMT par TÂCHE × CODE BUDGÉTAIRE : rattachement à la sous-tâche du Module 01, au compte et au titre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdmt_lignes', function (Blueprint $table) {
            $table->foreignId('tache_id')->nullable()->after('activite_id')->constrained('taches')->nullOnDelete();
            $table->foreignId('nomenclature_id')->nullable()->after('tache_id')->constrained('nomenclature_budgetaire')->nullOnDelete();
            $table->string('code', 20)->nullable()->after('nomenclature_id');
            $table->unsignedTinyInteger('titre')->nullable()->after('code');
            $table->unsignedInteger('ordre')->default(0);

            $table->unique(['cdmt_exercice_id', 'tache_id'], 'cdmt_lignes_unique_tache');
            $table->index(['cdmt_exercice_id', 'sous_programme_ep_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cdmt_lignes', function (Blueprint $table) {
            $table->dropUnique('cdmt_lignes_unique_tache');
            $table->dropIndex(['cdmt_exercice_id', 'sous_programme_ep_id']);
            $table->dropConstrainedForeignId('tache_id');
            $table->dropConstrainedForeignId('nomenclature_id');
            $table->dropColumn(['code', 'titre', 'ordre']);
        });
    }
};
