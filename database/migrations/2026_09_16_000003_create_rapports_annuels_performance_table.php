<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_annuels_performance', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('plan_strategique_ep_id')->constrained('plans_strategiques_ep')->restrictOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $table->foreignId('ppa_exercice_id')->nullable()->constrained('ppa_exercices')->nullOnDelete();

            $table->text('note_explicative')->nullable();
            $table->text('contexte_mise_oeuvre')->nullable();
            $table->text('difficultes_solutions')->nullable();
            $table->text('bilan_strategique_perspectives')->nullable();
            $table->text('lecons_apprises')->nullable(); // input pour le prochain cycle CDMT

            $table->string('statut')->default('brouillon');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_strategique_ep_id', 'exercice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_annuels_performance');
    }
};
