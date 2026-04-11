<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_memoire_depense', function (Blueprint $table) {
            $table->id();

            $table->foreignId('memoire_depense_id')
                ->constrained('memoires_depense')
                ->cascadeOnDelete();

            $table->integer('numero_ligne')->comment('N° d\'ordre de la ligne');

            // Nature de la dépense
            $table->string('nature_depense')->comment('Description de la nature');

            // Quantités et prix
            $table->decimal('quantite', 10, 3)->default(1);
            $table->decimal('prix_unitaire', 15, 2);

            // Montants calculés
            $table->decimal('montant_ht', 15, 2);
            $table->decimal('taux_tva', 5, 2)->default(19.25);
            $table->decimal('montant_tva', 15, 2);
            $table->decimal('taux_ir', 5, 2)->default(5.5);
            $table->decimal('montant_ir', 15, 2);
            $table->decimal('montant_ttc', 15, 2);
            $table->decimal('net_a_payer', 15, 2);

            $table->timestamps();

            // Index
            $table->index('memoire_depense_id');
            $table->index('numero_ligne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_memoire_depense');
    }
};
