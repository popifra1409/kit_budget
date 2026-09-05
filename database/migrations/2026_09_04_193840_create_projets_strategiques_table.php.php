<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets_strategiques', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // requis par HasWorkflow
            $table->foreignId('action_sous_programme_id')->constrained('actions_sous_programmes')->restrictOnDelete();

            // Lien optionnel vers la classification budgetaire existante (module Budget) —
            // permet de tracer la traduction budgetaire d'un projet/activite strategique,
            // sans dependance obligatoire ni impact sur la table 'activites' existante.
            $table->foreignId('activite_budgetaire_id')->nullable()
                ->constrained('activites')->nullOnDelete();

            $table->string('code');
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->date('date_debut_prevue')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide, en_cours, realise, abandonne
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projets_strategiques');
    }
};
