<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbmt_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbmt_exercice_id')->constrained('cbmt_exercices')->cascadeOnDelete();

            $table->enum('nature', ['ressource', 'depense']); // Tableau 9 vs Tableau 10
            $table->unsignedTinyInteger('titre'); // 1 a 4 (ressources) ou 1 a 6 (depenses)
            $table->string('libelle_titre'); // ex: "Recettes fiscales affectees", "Dettes de personnel"
            $table->string('source')->nullable(); // A/B/C - uniquement pour les ressources (Tableau 9)

            // Colonnes du tableau : N-1, N, N+1, N+2, N+3
            $table->decimal('montant_n_moins_1', 18, 2)->default(0);
            $table->decimal('montant_n', 18, 2)->default(0);
            $table->decimal('montant_n_plus_1', 18, 2)->default(0);
            $table->decimal('montant_n_plus_2', 18, 2)->default(0);
            $table->decimal('montant_n_plus_3', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbmt_lignes');
    }
};