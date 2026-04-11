<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers_fournisseurs', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('numero_dossier')->unique();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->onDelete('cascade');
            $table->foreignId('exercice_id')->constrained('exercices')->onDelete('cascade');

            // Type de dossier
            $table->enum('type_dossier', [
                'bon_commande',
                'marche',
                'decision_administrative',
                'prestation',
                'autre'
            ]);

            // Document principal (polymorphique)
            $table->string('document_principal_type')->nullable();
            $table->unsignedBigInteger('document_principal_id')->nullable();

            // Références
            $table->string('reference_principale')->nullable(); // Ex: BC-2025-001
            $table->string('objet');
            $table->text('description')->nullable();

            // Statut du dossier
            $table->enum('statut', [
                'ouvert',
                'en_cours',
                'attente_pieces',
                'attente_validation',
                'attente_paiement',
                'cloture',
                'annule'
            ])->default('ouvert');

            // Montants
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->decimal('montant_engage', 15, 2)->default(0);
            $table->decimal('montant_facture', 15, 2)->default(0);
            $table->decimal('montant_paye', 15, 2)->default(0);

            // Dates
            $table->date('date_ouverture');
            $table->date('date_cloture')->nullable();
            $table->date('date_limite_livraison')->nullable();

            // Responsables
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('createur_id')->constrained('users')->onDelete('cascade');

            // Observations
            $table->text('observations')->nullable();
            $table->text('motif_cloture')->nullable();

            // Métadonnées
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['fournisseur_id', 'statut']);
            $table->index(['exercice_id', 'statut']);
            $table->index('date_ouverture');
            $table->index(['document_principal_type', 'document_principal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers_fournisseurs');
    }
};
