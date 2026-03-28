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
        Schema::create('ordres_entree', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();                       // N° OE-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('reception_id')->constrained('receptions');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs');
            $table->foreignId('bon_commande_id')->nullable()->constrained('bons_commande');
            $table->date('date_oe');
            $table->string('numero_prise_en_charge')->nullable();     // N° inscrit au verso facture
            $table->date('date_prise_en_charge')->nullable();
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->text('observations')->nullable();
            $table->enum('statut', [
                'brouillon',
                'signe',           // Cosigné comptable + ordonnateur
                'transmis',        // Envoyé au budget pour paiement
                'annule',
            ])->default('brouillon');
            // Signatures
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->foreignId('ordonnateur_id')->constrained('users');
            $table->boolean('signe_comptable')->default(false);
            $table->boolean('signe_ordonnateur')->default(false);
            $table->date('date_signature')->nullable();
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
        Schema::dropIfExists('ordres_entree');
    }
};
