<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Trace de la suppression EXCEPTIONNELLE d'une ligne budgétaire de dépenses (budget adopté). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->text('motif_suppression')->nullable();
            $table->string('reference_suppression')->nullable();
            $table->foreignId('supprime_par')->nullable()->constrained('users')->nullOnDelete();
            $table->json('donnees_suppression')->nullable();   // AE/CP d'origine des sous-tâches, dotation
        });
    }

    public function down(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supprime_par');
            $table->dropColumn(['motif_suppression', 'reference_suppression', 'donnees_suppression']);
        });
    }
};
