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
        // 1. TABLE PROGRAMMES
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Code du programme (ex: P413)');
            $table->string('libelle')->comment('Libellé du programme');
            $table->text('description')->nullable();
            $table->integer('annee')->comment('Année budgétaire');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('code');
            $table->index('annee');
        });
        
        // 2. TABLE OBJECTIFS PRINCIPAUX
        Schema::create('objectifs_principaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->text('libelle')->comment('Objectif principal du programme');
            $table->integer('ordre')->default(0);
            $table->timestamps();
            
            $table->index('programme_id');
        });
        
        // 3. TABLE ACTIONS
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->string('code', 50)->comment('Code de l\'action (ex: A4)');
            $table->string('libelle')->comment('Libellé de l\'action');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('programme_id');
            $table->index('code');
        });
        
        // 4. TABLE OBJECTIFS SPÉCIFIQUES (par action)
        Schema::create('objectifs_specifiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('actions')->onDelete('cascade');
            $table->text('libelle')->comment('Objectif spécifique de l\'action');
            $table->integer('ordre')->default(0);
            $table->timestamps();
            
            $table->index('action_id');
        });
        
        // 5. TABLE ACTIVITÉS
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('actions')->onDelete('cascade');
            $table->string('code', 50)->comment('Code de l\'activité (ex: ACT1)');
            $table->string('libelle')->comment('Libellé de l\'activité');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('action_id');
            $table->index('code');
        });
        
        // 6. TABLE TÂCHES (niveau le plus bas, lié à la nomenclature)
        Schema::create('taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activite_id')->constrained('activites')->onDelete('cascade');
            $table->foreignId('nomenclature_id')->constrained('nomenclature_budgetaire')->onDelete('cascade');
            
            $table->string('code', 50)->comment('Code de la tâche');
            $table->text('libelle')->comment('Libellé de la tâche');
            $table->text('description')->nullable();
            
            // Informations de gestion
            $table->string('delai')->nullable()->comment('Délai de réalisation');
            $table->string('guichet')->nullable()->comment('Guichet responsable');
            $table->string('service_responsable')->nullable()->comment('Service responsable');
            
            // Montants budgétaires
            $table->decimal('ae', 15, 2)->default(0)->comment('Autorisation d\'Engagement');
            $table->decimal('cp', 15, 2)->default(0)->comment('Crédit de Paiement');
            
            // Résultats
            $table->text('resultat_attendu')->nullable()->comment('Résultat attendu de la tâche');
            $table->text('indicateur_resultat')->nullable()->comment('Indicateur de résultat');
            
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('activite_id');
            $table->index('nomenclature_id');
            $table->index('code');
        });
        
        // Commentaires
        DB::statement("COMMENT ON TABLE programmes IS 'Programmes budgétaires'");
        DB::statement("COMMENT ON TABLE objectifs_principaux IS 'Objectifs principaux des programmes'");
        DB::statement("COMMENT ON TABLE actions IS 'Actions des programmes'");
        DB::statement("COMMENT ON TABLE objectifs_specifiques IS 'Objectifs spécifiques des actions'");
        DB::statement("COMMENT ON TABLE activites IS 'Activités des actions'");
        DB::statement("COMMENT ON TABLE taches IS 'Tâches liées aux nomenclatures budgétaires avec AE/CP et résultats'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taches');
        Schema::dropIfExists('activites');
        Schema::dropIfExists('objectifs_specifiques');
        Schema::dropIfExists('actions');
        Schema::dropIfExists('objectifs_principaux');
        Schema::dropIfExists('programmes');
    }
};