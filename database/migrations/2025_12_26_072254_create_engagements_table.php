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
        // TABLE ENGAGEMENTS (Générique - pour BC, Décisions, Marchés, etc.)
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique()->comment('Numéro engagement (BE-YY-XXXXX)');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('restrict');

            // Type d'engagement polymorphique
            $table->string('engageable_type')->comment('BonCommande, DecisionAdministrative, Marche, etc.');
            $table->unsignedBigInteger('engageable_id')->comment('ID de l\'objet engagé');

            // Bénéficiaire
            $table->string('beneficiaire_type')->comment('Fournisseur, User (personnel), etc.');
            $table->unsignedBigInteger('beneficiaire_id')->comment('ID du bénéficiaire');

            // Dates
            $table->date('date_engagement')->comment('Date d\'engagement');
            $table->integer('exercice')->comment('Exercice budgétaire');

            // Objet
            $table->text('objet')->comment('Objet de l\'engagement');

            // Montants globaux
            $table->decimal('montant_engage', 15, 2)->comment('Montant total engagé');

            // Statut
            $table->enum('statut', [
                'provisoire',
                'definitif',
                'annule',
                'solde'
            ])->default('provisoire')->comment('Statut de l\'engagement');

            // Validation
            $table->foreignId('engage_par')->nullable()->constrained('users');
            $table->timestamp('date_validation')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('numero');
            $table->index('budget_id');
            $table->index('exercice');
            $table->index(['engageable_type', 'engageable_id']);
            $table->index(['beneficiaire_type', 'beneficiaire_id']);
            $table->index('statut');
        });

        // TABLE LIGNES D'ENGAGEMENT (Détail par nomenclature)
        Schema::create('lignes_engagement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->onDelete('cascade');
            $table->foreignId('nomenclature_id')->constrained('nomenclature_budgetaire')->onDelete('restrict');

            $table->integer('numero_ligne')->default(1);
            $table->text('libelle')->comment('Libellé de la ligne d\'engagement');
            $table->decimal('montant', 15, 2)->comment('Montant engagé sur cette nomenclature');

            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('engagement_id');
            $table->index('nomenclature_id');
        });

        DB::statement("COMMENT ON TABLE engagements IS 'Engagements budgétaires (BC, Décisions, Marchés, etc.)'");
        DB::statement("COMMENT ON TABLE lignes_engagement IS 'Détail des engagements par ligne budgétaire'");

        // Contraintes
        DB::statement("ALTER TABLE engagements ADD CONSTRAINT check_montant_positif CHECK (montant_engage > 0)");
        DB::statement("ALTER TABLE lignes_engagement ADD CONSTRAINT check_ligne_montant_positif CHECK (montant > 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_engagement');
        Schema::dropIfExists('engagements');
    }
};
