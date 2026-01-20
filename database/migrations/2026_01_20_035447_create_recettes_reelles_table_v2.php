<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recettes_reelles', function (Blueprint $table) {
            $table->id();

            // Références
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();

            $table->foreignId('prevision_recette_mensuelle_id')
                ->nullable()
                ->constrained('previsions_recettes_mensuelles')
                ->nullOnDelete()
                ->comment('Prévision mensuelle associée');

            $table->foreignId('nomenclature_id')
                ->nullable()
                ->constrained('nomenclature_budgetaire')
                ->nullOnDelete()
                ->comment('Nomenclature de recette (classe 7)');

            // Identification
            $table->string('numero', 50)->unique()->comment('Numéro de la recette');
            $table->string('code_nomenclature', 50)->comment('Code de la nomenclature');
            $table->string('libelle', 255)->comment('Libellé de la recette');

            // Période
            $table->integer('mois')->comment('Mois de la recette (1-12)');
            $table->integer('annee')->comment('Année de la recette');

            // Dates
            $table->date('date_recette')->comment('Date d\'encaissement');
            $table->date('date_comptabilisation')->nullable()->comment('Date de comptabilisation');

            // Montant
            $table->decimal('montant', 20, 2)->comment('Montant recouvré');

            // Informations complémentaires
            $table->string('payeur', 255)->nullable()->comment('Nom du payeur');
            $table->string('mode_paiement', 50)->nullable()->comment('Espèces, Chèque, Virement, etc.');
            $table->string('reference_paiement', 100)->nullable()->comment('N° chèque, référence virement, etc.');

            // Statut
            $table->enum('statut', [
                'prevue',
                'encaissee',
                'comptabilisee',
                'validee'
            ])->default('encaissee');

            // Validation
            $table->foreignId('validee_par')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Utilisateur ayant validé');

            $table->timestamp('validee_le')->nullable()->comment('Date de validation');

            // Observations
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('exercice_id');
            $table->index('prevision_recette_mensuelle_id');
            $table->index('nomenclature_id');
            $table->index('code_nomenclature');
            $table->index('date_recette');
            $table->index('statut');
            $table->index(['mois', 'annee']);
            $table->index(['exercice_id', 'date_recette']);
            $table->index(['exercice_id', 'mois', 'annee']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recettes_reelles');
    }
};
