<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_bons_commande_regies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bon_commande_regie_id')
                ->constrained('bons_commande_regies')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('numero_ligne')->default(1);
            $table->string('designation');
            $table->decimal('quantite',        10, 2)->default(1);
            $table->string('unite')->default('pièce');
            $table->decimal('prix_unitaire_ht', 15, 2)->default(0);

            $table->decimal('taux_tva',   5, 2)->default(19.25);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ht', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            $table->decimal('taux_ir',    5, 2)->default(0);
            $table->decimal('montant_ir', 15, 2)->default(0);
            $table->decimal('net_a_payer', 15, 2)->default(0);

            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bons_commande_regies');
    }
};
