<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cadre_logique', function (Blueprint $table) {
            $table->id();

            // Informations principales
            $table->string('code', 50)->comment('Code du cadre logique (ex: P413, A4, ACT1)');
            $table->text('libelle')->comment('Libellé/Description');

            // Niveau dans la hiérarchie
            $table->enum('niveau', [
                'programme',
                'objectif_general',
                'action',
                'objectif_specifique',
                'activite'
            ])->comment('Niveau dans le cadre logique');

            // Hiérarchie (auto-référence)
            $table->foreignId('parent_id')->nullable()
                ->constrained('cadre_logique')
                ->onDelete('cascade')
                ->comment('ID du parent dans la hiérarchie');

            // Liaison avec la nomenclature budgétaire
            // Une ligne budgétaire peut être liée à plusieurs niveaux du cadre logique
            // (programme, action, activité, etc.)

            // Indicateurs de performance (pour les activités)
            $table->text('indicateurs_resultat')->nullable()
                ->comment('Indicateurs de résultat (JSON array)');

            // Budget et suivi
            $table->decimal('budget_alloue', 15, 2)->nullable()
                ->comment('Budget alloué à ce niveau');

            $table->integer('annee')->comment('Année budgétaire');

            // Métadonnées
            $table->integer('ordre')->default(0)->comment('Ordre d\'affichage');
            $table->boolean('actif')->default(true)->comment('Actif ou non');

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('code');
            $table->index('niveau');
            $table->index('parent_id');
            $table->index('annee');
            $table->index(['annee', 'niveau']);
        });

        // Table de liaison entre nomenclature et cadre logique
        Schema::create('nomenclature_cadre_logique', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomenclature_id')
                ->constrained('nomenclature_budgetaire')
                ->onDelete('cascade');

            $table->foreignId('cadre_logique_id')
                ->constrained('cadre_logique')
                ->onDelete('cascade');

            // Une ligne budgétaire est liée à un niveau spécifique du cadre logique
            $table->enum('niveau_liaison', [
                'programme',
                'action',
                'activite'
            ])->comment('À quel niveau du cadre logique cette ligne est liée');

            $table->decimal('montant_affecte', 15, 2)->nullable()
                ->comment('Montant affecté à cette liaison');

            $table->timestamps();

            // Contrainte unique
            $table->unique(['nomenclature_id', 'cadre_logique_id'], 'unique_nomenclature_cadre');

            // Index
            $table->index('nomenclature_id');
            $table->index('cadre_logique_id');
        });

        // Commentaires
        DB::statement("COMMENT ON TABLE cadre_logique IS 'Cadre logique du projet de performances (Programmes, Objectifs, Actions, Activités)'");
        DB::statement("COMMENT ON TABLE nomenclature_cadre_logique IS 'Liaison entre lignes budgétaires et cadre logique'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomenclature_cadre_logique');
        Schema::dropIfExists('cadre_logique');
    }
};
