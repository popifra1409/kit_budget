<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('previsions_budget_programme', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exercice_id');       // Exercice de référence
            $table->unsignedBigInteger('nomenclature_id');   // Ligne budgétaire
            $table->integer('annee');                        // Année concernée
            $table->enum('type', ['prevision', 'realisation']);
            $table->enum('categorie', ['fonctionnement', 'investissement'])->default('fonctionnement');
            $table->decimal('montant', 20, 2)->default(0);
            $table->boolean('est_saisi_manuellement')->default(false); // false = calculé depuis DB
            $table->text('observations')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['exercice_id', 'nomenclature_id', 'annee', 'type'], 'uniq_prevision');
            $table->foreign('exercice_id')->references('id')->on('exercices');
            $table->foreign('nomenclature_id')->references('id')->on('nomenclature_budgetaire');
        });

        // Table collectifs budgétaires
        Schema::create('collectifs_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exercice_id');
            $table->string('numero');              // Ex: CB-2026-01
            $table->string('libelle');             // Ex: Collectif de mars 2026
            $table->date('date_collectif');
            $table->enum('statut', ['brouillon', 'valide'])->default('brouillon');
            $table->text('objet')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('exercice_id')->references('id')->on('exercices');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('previsions_budget_programme');
        Schema::dropIfExists('collectifs_budgetaires');
    }
};