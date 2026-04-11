<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_mercuriales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->onDelete('cascade');
            $table->string('code_reference')->unique();
            $table->string('designation');
            $table->string('unite')->default('pièce');
            $table->decimal('prix_reference', 15, 2)->default(0);
            $table->string('rubrique')->nullable();
            $table->string('sous_rubrique')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['exercice_id', 'actif']);
            $table->index('rubrique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_mercuriales');
    }
};
