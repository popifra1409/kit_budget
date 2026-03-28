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
        Schema::create('expressions_besoins', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();                       // N° EB-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->string('service_demandeur');                      // Service émetteur
            $table->foreignId('responsable_service_id')->constrained('users'); // Responsable service
            $table->foreignId('comptable_matieres_id')->constrained('users');  // Comptable-matières
            $table->date('date_expression');
            $table->text('observations')->nullable();
            $table->enum('statut', [
                'brouillon',       // En cours de saisie
                'soumis',          // Soumis au comptable-matières
                'valide',          // Validé par ordonnateur
                'en_commande',     // BC généré
                'satisfait',       // Réception faite
                'annule',
            ])->default('brouillon');
            $table->foreignId('ordonnateur_id')->nullable()->constrained('users');
            $table->date('date_validation')->nullable();
            // Lien vers BC généré
            $table->foreignId('bon_commande_id')->nullable()->constrained('bons_commande');
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
        Schema::dropIfExists('expressions_besoins');
    }
};
