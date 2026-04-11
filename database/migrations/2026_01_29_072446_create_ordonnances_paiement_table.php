<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordonnances_paiement', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('numero')->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->onDelete('cascade');

            // Type d'ordonnance
            $table->enum('type_ordonnance', ['standard', 'impot'])->default('standard');

            // Lien avec engagement
            $table->foreignId('engagement_id')->constrained('engagements')->onDelete('cascade');

            // Bénéficiaire (polymorphique)
            $table->string('beneficiaire_type')->nullable();
            $table->unsignedBigInteger('beneficiaire_id')->nullable();

            // Objet
            $table->text('objet');

            // Montants
            $table->decimal('montant_brut', 15, 2)->default(0);
            $table->decimal('montant_impot', 15, 2)->default(0);
            $table->decimal('montant_net', 15, 2)->default(0);
            $table->decimal('montant_pec', 15, 2)->default(0); // Pour PEC Médical

            // Dates et références
            $table->date('date_emission');
            $table->string('mois_emission')->nullable(); // Format: MM
            $table->string('numero_bon')->nullable();
            $table->string('numero_emission')->nullable();
            $table->string('numero_op')->nullable();
            $table->string('periode')->nullable(); // Format: MM/YYYY

            // Statut
            $table->enum('statut', [
                'brouillon',
                'emise',
                'visee',
                'payee',
                'annulee'
            ])->default('brouillon');

            // Paiement
            $table->date('date_paiement')->nullable();
            $table->string('reference_paiement')->nullable();

            // Observations
            $table->text('observations')->nullable();

            // Métadonnées
            $table->json('metadata')->nullable();

            // Créateur
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['engagement_id', 'statut']);
            $table->index(['exercice_id', 'type_ordonnance']);
            $table->index(['beneficiaire_type', 'beneficiaire_id']);
            $table->index('date_emission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnances_paiement');
    }
};
