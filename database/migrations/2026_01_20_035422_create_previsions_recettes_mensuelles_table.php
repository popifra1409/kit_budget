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
        Schema::create('previsions_recettes_mensuelles', function (Blueprint $table) {
            $table->id();

            // Références
            $table->foreignId('ligne_prevision_recette_id')
                ->constrained('lignes_previsions_recettes')
                ->cascadeOnDelete()
                ->comment('Ligne de prévision annuelle parente');

            $table->foreignId('exercice_id')
                ->constrained('exercices')
                ->cascadeOnDelete();

            // Période
            $table->integer('mois')->comment('Mois (1-12)');
            $table->integer('annee')->comment('Année');

            // Montants
            $table->decimal('montant_prevu', 20, 2)->default(0)->comment('Montant prévu pour ce mois');
            $table->decimal('montant_recouvre', 20, 2)->default(0)->comment('Montant réellement recouvré ce mois');

            // Calculs (mis à jour automatiquement)
            $table->decimal('ecart', 20, 2)->default(0)->comment('Écart mois = recouvré - prévu');
            $table->decimal('taux_realisation', 8, 2)->default(0)->comment('% de réalisation du mois');
            $table->decimal('montant_cumule_prevu', 20, 2)->default(0)->comment('Cumulé prévu depuis janvier');
            $table->decimal('montant_cumule_recouvre', 20, 2)->default(0)->comment('Cumulé recouvré depuis janvier');
            $table->decimal('taux_realisation_cumule', 8, 2)->default(0)->comment('% réalisation cumulé');

            // État
            $table->boolean('actif')->default(true);

            // Observations
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index et contraintes
            $table->index('ligne_prevision_recette_id');
            $table->index('exercice_id');
            $table->index(['mois', 'annee']);
            $table->index(['ligne_prevision_recette_id', 'mois', 'annee']);

            // Unicité : une seule prévision par ligne par mois
            $table->unique(['ligne_prevision_recette_id', 'mois', 'annee'], 'unique_ligne_mois');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('previsions_recettes_mensuelles');
    }
};
