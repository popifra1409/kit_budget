<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbmt_exercices', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // requis par HasWorkflow
            $table->foreignId('plan_strategique_ep_id')->constrained('plans_strategiques_ep')->restrictOnDelete();

            // Exercice de reference (N) - le cadrage porte sur N-1, N, N+1, N+2/N+3
            $table->foreignId('exercice_reference_id')->constrained('exercices')->restrictOnDelete();

            $table->date('date_lettre_cadrage')->nullable(); // limite reglementaire : 15 juin N
            $table->text('hypotheses_ressources')->nullable(); // narratif justifiant les projections
            $table->text('commentaire_soutenabilite')->nullable();

            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_strategique_ep_id', 'exercice_reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbmt_exercices');
    }
};
