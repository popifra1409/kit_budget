<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres d'exécution budgétaire (délais, plafonds, décideurs...) AVEC HISTORIQUE.
 * Chaque changement crée une nouvelle ligne datée (date d'effet) : la valeur applicable
 * à une date donnée est la plus récente dont la date d'effet est antérieure ou égale.
 * Les liquidations, mouvements, etc. déjà enregistrés gardent la règle de leur date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_execution', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80);
            $table->text('valeur')->nullable();
            $table->date('date_effet');
            $table->string('motif')->nullable();   // ex. « Instruction du 22 janvier 2026 »
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cle', 'date_effet']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_execution');
    }
};
