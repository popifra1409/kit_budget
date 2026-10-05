<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROCÉDURE EXCEPTIONNELLE : paiement sans ordonnancement préalable.
 * Circuit : autorise (ordonnateur) → paye (agent comptable) → regularise (OP de régularisation).
 * Ne supprime jamais les obligations de justification, de traçabilité et de régularisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_exceptionnels', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();

            // Objet et imputation
            $table->foreignId('ligne_budgetaire_id')->nullable()->constrained('lignes_budgetaires')->nullOnDelete();
            $table->string('beneficiaire_type')->nullable();          // Fournisseur / Personnel
            $table->unsignedBigInteger('beneficiaire_id')->nullable();
            $table->decimal('montant', 18, 2);
            $table->string('objet');
            $table->string('motif_urgence', 40);
            $table->text('justification');
            $table->string('fondement')->nullable();                   // texte réglementaire invoqué

            // Autorisation (ordonnateur)
            $table->foreignId('autorise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_autorisation')->nullable();
            $table->date('date_autorisation')->nullable();
            $table->string('piece_autorisation')->nullable();

            // Paiement (agent comptable)
            $table->foreignId('paye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_paiement')->nullable();
            $table->string('mode_paiement', 40)->nullable();
            $table->string('reference_paiement')->nullable();
            $table->string('piece_paiement')->nullable();

            // Régularisation — délai FIGÉ au paiement
            $table->unsignedInteger('delai_regularisation_jours')->nullable();
            $table->date('date_limite_regularisation')->nullable();
            $table->foreignId('ordonnance_paiement_id')->nullable()->constrained('ordonnances_paiement')->nullOnDelete();
            $table->foreignId('regularise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_regularisation')->nullable();
            $table->text('observations')->nullable();

            $table->string('statut', 20)->default('brouillon');   // brouillon → autorise → paye → regularise | annule
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'date_limite_regularisation']);
            $table->index(['beneficiaire_type', 'beneficiaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_exceptionnels');
    }
};
