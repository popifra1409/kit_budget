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
        // 1. TABLE BONS DE COMMANDE (En-tête)
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique()->comment('Numéro BC (ex: BC-2026-0001)');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('restrict')
                ->comment('Budget de rattachement');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('restrict')
                ->comment('Fournisseur');
            $table->foreignId('service_demandeur_id')->constrained('services')->onDelete('restrict')
                ->comment('Service demandeur/bénéficiaire');

            // Dates
            $table->date('date_emission')->comment('Date d\'émission du BC');
            $table->date('date_livraison_prevue')->nullable()->comment('Date de livraison prévue');
            $table->date('date_livraison_effective')->nullable()->comment('Date de livraison effective');

            // Objet
            $table->text('objet')->comment('Objet du bon de commande');
            $table->text('observations')->nullable();

            // Montants
            $table->decimal('montant_ht', 15, 2)->default(0)->comment('Montant HT');
            $table->decimal('montant_tva', 15, 2)->default(0)->comment('Montant TVA');
            $table->decimal('montant_ttc', 15, 2)->default(0)->comment('Montant TTC');

            // Statut et workflow
            $table->enum('statut', [
                'brouillon',
                'valide',
                'engage',
                'en_cours',
                'livre_partiellement',
                'livre',
                'annule'
            ])->default('brouillon');

            // Validation
            $table->foreignId('valide_par')->nullable()->constrained('users');
            $table->timestamp('date_validation')->nullable();

            // Engagement budgétaire
            $table->boolean('engage')->default(false)->comment('Budget engagé');
            $table->decimal('montant_engage', 15, 2)->default(0);
            $table->timestamp('date_engagement')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('numero');
            $table->index('statut');
            $table->index('budget_id');
            $table->index('fournisseur_id');
            $table->index('date_emission');
        });

        // 2. TABLE LIGNES BON DE COMMANDE
        Schema::create('lignes_bon_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')->constrained('bons_commande')->onDelete('cascade');
            $table->foreignId('nomenclature_id')->constrained('nomenclature_budgetaire')->onDelete('restrict')
                ->comment('Ligne budgétaire imputée');

            $table->integer('numero_ligne')->default(1)->comment('Numéro de ligne');
            $table->text('designation')->comment('Désignation de l\'article/service');
            $table->string('unite')->nullable()->comment('Unité (kg, m, pièce, etc.)');
            $table->decimal('quantite', 15, 3)->default(1)->comment('Quantité');
            $table->decimal('prix_unitaire_ht', 15, 2)->comment('Prix unitaire HT');
            $table->decimal('montant_ht', 15, 2)->comment('Montant HT = Qté × PU');
            $table->decimal('taux_tva', 5, 2)->default(19.25)->comment('Taux TVA (%)');
            $table->decimal('montant_tva', 15, 2)->default(0)->comment('Montant TVA');
            $table->decimal('montant_ttc', 15, 2)->comment('Montant TTC');

            // Livraison
            $table->decimal('quantite_livree', 15, 3)->default(0)->comment('Quantité livrée');
            $table->decimal('quantite_restante', 15, 3)->default(0)->comment('Quantité restante');

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('bon_commande_id');
            $table->index('nomenclature_id');
        });

        DB::statement("COMMENT ON TABLE bons_commande IS 'Bons de commande - En-tête'");
        DB::statement("COMMENT ON TABLE lignes_bon_commande IS 'Lignes de détail des bons de commande'");

        // Contraintes
        DB::statement("ALTER TABLE lignes_bon_commande ADD CONSTRAINT check_quantites_positives CHECK (
            quantite > 0 AND 
            prix_unitaire_ht >= 0 AND
            quantite_livree >= 0 AND
            quantite_restante >= 0
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_bon_commande');
        Schema::dropIfExists('bons_commande');
    }
};
