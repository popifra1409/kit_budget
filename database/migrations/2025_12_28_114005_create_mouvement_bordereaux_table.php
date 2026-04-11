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
        Schema::create('mouvement_bordereaux', function (Blueprint $table) {
            $table->id();

            // Bordereau concerné - Détecter le nom de la table automatiquement
            $bordereauTableName = (new \App\Models\BordereauEngagement())->getTable();
            $table->foreignId('bordereau_id')
                ->constrained($bordereauTableName)
                ->cascadeOnDelete();

            // Qui a effectué l'action
            $table->foreignId('effectue_par_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Type d'action
            $table->enum('action', [
                'creation',
                'soumission',
                'transmission',
                'reception',
                'validation',
                'rejet',
                'retour',
                'cloture',
                'annulation',
            ]);

            // Destinataire (pour transmission)
            $table->foreignId('destinataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Statuts avant/après
            $table->string('statut_avant')->nullable();
            $table->string('statut_apres')->nullable();

            // Observations
            $table->text('observations')->nullable();

            // Motif (pour rejet)
            $table->text('motif')->nullable();

            // Métadonnées JSON (optionnel)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index('bordereau_id');
            $table->index('effectue_par_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvement_bordereaux');
    }
};
