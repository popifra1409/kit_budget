<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmissions', function (Blueprint $table) {
            $table->id();

            // Document transmis (polymorphique)
            $table->string('document_type'); // BonCommande, Engagement, etc.
            $table->unsignedBigInteger('document_id');

            // Acteurs
            $table->foreignId('expediteur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('destinataire_id')->constrained('users')->onDelete('cascade');

            // Nature de la transmission
            $table->enum('action_attendue', [
                'validation',
                'engagement',
                'verification',
                'correction',
                'signature',
                'information',
                'liquidation',
                'paiement'
            ]);

            // Statut
            $table->enum('statut', [
                'en_attente',
                'traite',
                'rejete',
                'annule'
            ])->default('en_attente');

            // Détails
            $table->text('commentaire')->nullable();
            $table->text('reponse')->nullable();
            $table->string('priorite')->default('normale'); // basse, normale, haute, urgente
            $table->date('date_limite')->nullable();

            // Dates
            $table->timestamp('date_transmission');
            $table->timestamp('date_traitement')->nullable();
            $table->timestamp('date_lecture')->nullable();

            // Métadonnées
            $table->json('documents_joints')->nullable(); // Chemins vers fichiers joints
            $table->json('metadata')->nullable(); // Données additionnelles

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['document_type', 'document_id']);
            $table->index(['destinataire_id', 'statut']);
            $table->index('date_transmission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmissions');
    }
};
