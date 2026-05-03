<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_depense_decisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regie_avance_id')
                ->constrained('regies_avances')
                ->cascadeOnDelete();

            $table->foreignId('decision_administrative_id')
                ->constrained('decisions_administratives')
                ->restrictOnDelete();

            $table->foreignId('nomenclature_id')
                ->constrained('nomenclature_budgetaire')
                ->restrictOnDelete();

            $table->foreignId('ligne_budgetaire_id')
                ->constrained('lignes_budgetaires')
                ->restrictOnDelete();

            // Montant de cette DA pour ce menu dépense
            $table->decimal('montant_da', 15, 2)->default(0);

            $table->timestamps();

            // Une DA ne peut alimenter qu'une fois le même menu dépense
            $table->unique([
                'regie_avance_id',
                'decision_administrative_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_depense_decisions');
    }
};
