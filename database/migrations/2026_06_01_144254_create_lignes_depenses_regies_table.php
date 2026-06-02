<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_depenses_regies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depense_regie_id')->constrained('depenses_regies')->cascadeOnDelete();
            $table->integer('numero_ligne')->default(1);
            $table->string('nature_depense');
            $table->decimal('quantite', 10, 4)->default(1);
            $table->decimal('prix_unitaire', 15, 4)->default(0);
            $table->decimal('montant_nap_input', 15, 2)->nullable();
            $table->decimal('taux_tva', 5, 2)->default(19.25);
            $table->decimal('taux_ir', 5, 2)->default(5.5);
            $table->decimal('montant_ht', 15, 2)->default(0);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            $table->decimal('montant_ir', 15, 2)->default(0);
            $table->decimal('montant_net', 15, 2)->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_depenses_regies');
    }
};
