<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisions_lignes_regies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('decaissement_regie_id')
                ->constrained('decaissements_regies')
                ->cascadeOnDelete();

            $table->foreignId('ligne_regie_avance_id')
                ->constrained('lignes_regies_avances')
                ->cascadeOnDelete();

            // Montant provisionné sur cette ligne depuis ce décaissement
            $table->decimal('montant_provisionne', 15, 2)->default(0);
            $table->decimal('montant_consomme',    15, 2)->default(0);
            $table->decimal('montant_disponible',  15, 2)->default(0);

            $table->timestamps();

            $table->unique(['decaissement_regie_id', 'ligne_regie_avance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisions_lignes_regies');
    }
};
