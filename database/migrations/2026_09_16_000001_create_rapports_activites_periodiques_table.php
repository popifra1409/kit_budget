<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_activites_periodiques', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('activite_id')->constrained('activites')->restrictOnDelete();

            $table->string('periode'); // ex: '2026-01', '2026-T1'
            $table->enum('type_periode', ['mensuel', 'trimestriel'])->default('mensuel');

            $table->decimal('poids_activite', 5, 2)->nullable(); // % de contribution a l'objectif de l'action
            $table->text('problemes_rencontres')->nullable();
            $table->text('solutions_proposees')->nullable();

            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['activite_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_activites_periodiques');
    }
};
