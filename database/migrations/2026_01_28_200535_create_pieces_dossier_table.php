<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces_dossier', function (Blueprint $table) {
            $table->id();

            // Dossier parent
            $table->foreignId('dossier_id')->constrained('dossiers_fournisseurs')->onDelete('cascade');

            // Type de pièce
            $table->enum('type_piece', [
                'bon_commande',
                'engagement',
                'facture_proforma',
                'facture_definitive',
                'bordereau_livraison',
                'pv_reception',
                'certificat_service_fait',
                'ordre_paiement',
                'justificatif_paiement',
                'piece_comptable',
                'autre_document'
            ]);

            // Document lié (polymorphique - optionnel)
            $table->string('document_type')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();

            // Fichier
            $table->string('nom_fichier');
            $table->string('chemin_fichier');
            $table->string('type_mime')->nullable();
            $table->bigInteger('taille')->nullable(); // en octets

            // Validation
            $table->boolean('valide')->default(false);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();

            // Métadonnées
            $table->text('commentaire')->nullable();
            $table->json('metadata')->nullable();

            // Ajouté par
            $table->foreignId('ajoute_par')->constrained('users')->onDelete('cascade');
            $table->timestamp('date_ajout');

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['dossier_id', 'type_piece']);
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_dossier');
    }
};
