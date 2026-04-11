<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taux_ir', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regime_fiscal_id')->constrained('regimes_fiscaux')->onDelete('cascade');

            // Pour les tranches (si type_calcul_ir = 'tranche')
            $table->decimal('montant_min', 15, 2)->nullable();
            $table->decimal('montant_max', 15, 2)->nullable();

            // Taux ou montant
            $table->decimal('taux', 5, 2)->nullable(); // En pourcentage
            $table->decimal('montant_fixe', 15, 2)->nullable(); // Montant forfaitaire

            // Période de validité
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();

            // Statut
            $table->boolean('actif')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['regime_fiscal_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taux_ir');
    }
};
