<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercices', function (Blueprint $table) {
            $table->id();
            $table->integer('annee')->unique()->comment('Année budgétaire (ex: 2025)');
            $table->string('libelle')->comment('Ex: Exercice budgétaire 2025');
            $table->text('description')->nullable();

            // Statut du cycle de vie
            $table->enum('statut', ['brouillon', 'actif', 'cloture', 'archive'])
                ->default('brouillon')
                ->comment('Cycle: brouillon → actif → cloture → archive');

            // Dates importantes
            $table->date('date_debut')->comment('Date de début (généralement 1er janvier)');
            $table->date('date_fin')->comment('Date de fin (généralement 31 décembre)');
            $table->date('date_cloture')->nullable()->comment('Date de clôture effective');
            $table->date('date_archive')->nullable()->comment('Date d\'archivage');

            // Utilisateurs responsables
            $table->foreignId('cloture_par')->nullable()->constrained('users')->comment('Utilisateur ayant clôturé');
            $table->foreignId('archive_par')->nullable()->constrained('users')->comment('Utilisateur ayant archivé');

            // Indicateurs
            $table->boolean('actif')->default(false)->comment('Un seul exercice peut être actif à la fois');
            $table->boolean('reconduction_effectuee')->default(false)->comment('Données reconduites depuis exercice précédent');
            $table->foreignId('exercice_source_id')->nullable()->constrained('exercices')->comment('Exercice source de reconduction');

            // Métadonnées
            $table->json('statistiques')->nullable()->comment('Stats: nb programmes, budgets, engagements...');
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('annee');
            $table->index('statut');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercices');
    }
};
