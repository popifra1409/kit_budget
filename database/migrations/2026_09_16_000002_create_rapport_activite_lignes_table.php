<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapport_activite_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapport_activite_periodique_id')->constrained('rapports_activites_periodiques')->cascadeOnDelete();
            $table->foreignId('tache_id')->nullable()->constrained('taches')->nullOnDelete();

            $table->enum('nature', ['tache', 'moyen']); // Annexe 9 : 2 tableaux distincts
            $table->string('libelle');
            $table->string('unite')->nullable();

            $table->decimal('prevision', 15, 2)->default(0);
            $table->decimal('realisation', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapport_activite_lignes');
    }
};
