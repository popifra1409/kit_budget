<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avenants', function (Blueprint $table) {
            $table->id();

            // Document source (BC ou DA original)
            $table->morphs('document_source');      // document_source_type + document_source_id

            // Document corrigé (nouveau BC ou DA)
            $table->morphs('document_corrige');     // document_corrige_type + document_corrige_id

            // Engagement original
            $table->foreignId('engagement_original_id')
                ->constrained('engagements')->cascadeOnDelete();

            // Engagement différentiel (peut être null si même montant/ligne)
            $table->foreignId('engagement_differentiel_id')
                ->nullable()->constrained('engagements')->nullOnDelete();

            $table->integer('numero_avenant')->default(1);
            $table->string('motif');
            $table->enum('type_correction', [
                'montant',        // Correction de montant uniquement
                'nomenclature',   // Changement de ligne budgétaire
                'mixte',          // Montant + nomenclature
                'objet',          // Correction d'objet seulement (pas d'impact budget)
            ]);

            // Différentiel budgétaire
            $table->decimal('montant_original', 15, 2)->default(0);
            $table->decimal('montant_corrige', 15, 2)->default(0);
            $table->decimal('delta_montant', 15, 2)->default(0); // corrigé - original

            $table->unsignedBigInteger('nomenclature_originale_id')->nullable();
            $table->unsignedBigInteger('nomenclature_corrigee_id')->nullable();

            $table->enum('statut', ['brouillon', 'applique', 'annule'])->default('brouillon');
            $table->foreignId('applique_par')->nullable()->constrained('users');
            $table->timestamp('date_application')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avenants');
    }
};
