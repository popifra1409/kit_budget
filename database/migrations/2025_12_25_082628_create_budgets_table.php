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
        // 1. TABLE BUDGETS (Budget global par exercice)
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Code du budget (ex: BUD-2026)');
            $table->string('libelle')->comment('Libellé du budget');
            $table->integer('exercice')->comment('Exercice budgétaire');
            $table->date('date_adoption')->nullable()->comment('Date d\'adoption du budget');
            $table->text('observations')->nullable();
            $table->enum('statut', ['elaboration', 'adopte', 'execution', 'cloture'])->default('elaboration');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('exercice');
            $table->index('statut');
        });

        // 2. TABLE LIGNES BUDGETAIRES (Détail par nomenclature)
        Schema::create('lignes_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('nomenclature_id')->constrained('nomenclature_budgetaire')->onDelete('cascade');

            // Montants budgétaires
            $table->decimal('budget_initial', 15, 2)->default(0)->comment('Budget initial voté');
            $table->decimal('virements_entrants', 15, 2)->default(0)->comment('Virements reçus');
            $table->decimal('virements_sortants', 15, 2)->default(0)->comment('Virements envoyés');
            $table->decimal('budget_rectifie', 15, 2)->default(0)->comment('Budget après virements');

            // Consommations
            $table->decimal('engage', 15, 2)->default(0)->comment('Montant engagé (BC)');
            $table->decimal('ordonne', 15, 2)->default(0)->comment('Montant ordonné');
            $table->decimal('liquide', 15, 2)->default(0)->comment('Montant liquidé');
            $table->decimal('paye', 15, 2)->default(0)->comment('Montant effectivement payé');

            // Disponibles (calculés)
            $table->decimal('disponible_engagement', 15, 2)->default(0)->comment('Disponible pour engagement');
            $table->decimal('disponible_ordonnancement', 15, 2)->default(0)->comment('Disponible pour ordonnancement');

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Contrainte unique : une seule ligne par nomenclature et par budget
            $table->unique(['budget_id', 'nomenclature_id']);

            $table->index('budget_id');
            $table->index('nomenclature_id');
        });

        // 3. TABLE VIREMENTS BUDGETAIRES (Transferts entre lignes)
        Schema::create('virements_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique()->comment('Numéro du virement (VIR-YYYY-XXXXX)');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');

            // Ligne source (qui perd du budget)
            $table->foreignId('ligne_source_id')->constrained('lignes_budgetaires')->onDelete('cascade');

            // Ligne destination (qui reçoit du budget)
            $table->foreignId('ligne_destination_id')->constrained('lignes_budgetaires')->onDelete('cascade');

            $table->decimal('montant', 15, 2)->comment('Montant viré');
            $table->date('date_virement')->comment('Date du virement');
            $table->text('motif')->comment('Motif/Justification du virement');
            $table->string('reference_decision')->nullable()->comment('Référence décision autorisant le virement');

            $table->enum('statut', ['en_attente', 'approuve', 'execute', 'rejete'])->default('en_attente');
            $table->foreignId('valide_par')->nullable()->constrained('users')->comment('Utilisateur ayant validé');
            $table->timestamp('date_validation')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('budget_id');
            $table->index('numero');
            $table->index('statut');
        });

        // Commentaires
        DB::statement("COMMENT ON TABLE budgets IS 'Budgets par exercice'");
        DB::statement("COMMENT ON TABLE lignes_budgetaires IS 'Lignes budgétaires détaillées par nomenclature'");
        DB::statement("COMMENT ON TABLE virements_budgetaires IS 'Virements/Transferts de crédits entre lignes budgétaires'");

        // Contraintes de vérification
        DB::statement("ALTER TABLE lignes_budgetaires ADD CONSTRAINT check_montants_positifs CHECK (
            budget_initial >= 0 AND 
            virements_entrants >= 0 AND 
            virements_sortants >= 0 AND 
            engage >= 0 AND 
            ordonne >= 0 AND 
            liquide >= 0 AND 
            paye >= 0
        )");

        DB::statement("ALTER TABLE virements_budgetaires ADD CONSTRAINT check_virement_montant_positif CHECK (montant > 0)");
        DB::statement("ALTER TABLE virements_budgetaires ADD CONSTRAINT check_virement_lignes_differentes CHECK (ligne_source_id != ligne_destination_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virements_budgetaires');
        Schema::dropIfExists('lignes_budgetaires');
        Schema::dropIfExists('budgets');
    }
};
