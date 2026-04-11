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
        Schema::create('lignes_previsions_recettes', function (Blueprint $table) {
            $table->id();

            // Références
            $table->foreignId('prevision_recette_id')
                ->constrained('previsions_recettes')
                ->cascadeOnDelete();

            $table->foreignId('nomenclature_id')
                ->nullable()
                ->constrained('nomenclature_budgetaire')
                ->nullOnDelete()
                ->comment('Nomenclature de recette (classe 7)');

            // Identification
            $table->string('code_nomenclature', 50)->comment('Code de la nomenclature');
            $table->string('libelle_nomenclature', 255)->comment('Libellé de la nomenclature');

            // Montants
            $table->decimal('montant_prevu_initial', 20, 2)->default(0)->comment('Montant initial prévu');
            $table->decimal('montant_rectifie', 20, 2)->default(0)->comment('Montant après rectifications');
            $table->decimal('montant_recouvre', 20, 2)->default(0)->comment('Montant effectivement recouvré');

            // Calculs (à maintenir via observers/événements)
            $table->decimal('ecart', 20, 2)->default(0)->comment('Écart = recouvré - rectifié');
            $table->decimal('taux_recouvrement', 8, 2)->default(0)->comment('% de recouvrement');

            // Ordre d'affichage
            $table->integer('ordre')->default(0);

            // État
            $table->boolean('actif')->default(true);

            // Observations
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('prevision_recette_id');
            $table->index('nomenclature_id');
            $table->index('code_nomenclature');
            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_previsions_recettes');
    }
};
