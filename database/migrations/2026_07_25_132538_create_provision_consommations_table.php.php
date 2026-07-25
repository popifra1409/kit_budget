<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provision_consommations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provision_ligne_regie_id')
                ->constrained('provisions_lignes_regies')
                ->cascadeOnDelete();
            $table->foreignId('bon_commande_regie_id')
                ->constrained('bons_commande_regies')
                ->cascadeOnDelete();
            $table->decimal('montant', 15, 2);
            $table->timestamps();

            $table->index(['bon_commande_regie_id']);
            $table->index(['provision_ligne_regie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_consommations');
    }
};
