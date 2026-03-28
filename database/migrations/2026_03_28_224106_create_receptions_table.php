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
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();                       // N° REC-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('bon_commande_id')->nullable()->constrained('bons_commande');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs');
            $table->foreignId('expression_besoin_id')->nullable()->constrained('expressions_besoins');
            $table->date('date_reception');
            $table->string('numero_bordereau_livraison')->nullable();  // N° BL fournisseur
            $table->date('date_bordereau_livraison')->nullable();
            $table->string('numero_facture')->nullable();
            $table->date('date_facture')->nullable();
            $table->decimal('montant_facture', 15, 2)->default(0);
            // Commission de réception
            $table->foreignId('president_commission_id')->constrained('users');  // Ordonnateur-matières
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->foreignId('service_technique_id')->nullable()->constrained('users');
            $table->foreignId('representant_prestataire')->nullable();  // Nom libre
            $table->string('representant_prestataire_nom')->nullable();
            $table->text('observations')->nullable();
            $table->text('reserves')->nullable();                     // Réserves éventuelles
            $table->enum('statut', [
                'brouillon',
                'pv_signe',        // PV signé par toutes les parties
                'integre',         // Intégré en stock (OE créé)
                'annule',
            ])->default('brouillon');
            // Signatures
            $table->boolean('signe_prestataire')->default(false);
            $table->boolean('signe_technique')->default(false);
            $table->boolean('signe_comptable')->default(false);
            $table->boolean('signe_ordonnateur')->default(false);
            $table->date('date_pv')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receptions');
    }
};
