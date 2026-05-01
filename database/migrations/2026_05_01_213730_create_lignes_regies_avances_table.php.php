<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_regies_avances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regie_avance_id')
                ->constrained('regies_avances')
                ->cascadeOnDelete();

            $table->foreignId('nomenclature_id')
                ->constrained('nomenclature_budgetaire') 
                ->restrictOnDelete();

            $table->foreignId('ligne_budgetaire_id')
                ->constrained('lignes_budgetaires')
                ->restrictOnDelete();

            // ── Montants mini-budget ─────────────────────
            $table->decimal('montant_alloue',    15, 2)->default(0);
            $table->decimal('montant_consomme',  15, 2)->default(0);
            $table->decimal('montant_disponible', 15, 2)->default(0);

            $table->timestamps();

            // ── Unicité : une nomenclature par régie ─────
            $table->unique(['regie_avance_id', 'nomenclature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_regies_avances');
    }
};
