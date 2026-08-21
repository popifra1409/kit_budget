<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lignes_factures_proforma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_proforma_id')->constrained('factures_proforma')->cascadeOnDelete();
            $table->foreignId('reference_mercuriale_id')->nullable()
                ->constrained('reference_mercuriales')->nullOnDelete();
            $table->integer('numero_ligne')->default(1);
            $table->string('designation');
            $table->string('unite', 30)->nullable();
            $table->decimal('quantite', 12, 3)->default(1);
            $table->decimal('prix_unitaire_ht', 15, 2)->default(0);
            $table->decimal('montant_ht', 15, 2)->default(0);
            $table->decimal('taux_tva', 5, 2)->default(19.25);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            // ✅ Traçabilité — vers quel(s) BC cette ligne a-t-elle été reprise
            $table->foreignId('ligne_bon_commande_id')->nullable()
                ->constrained('lignes_bon_commande')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_factures_proforma');
    }
};
