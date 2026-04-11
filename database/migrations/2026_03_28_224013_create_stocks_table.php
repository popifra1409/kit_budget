<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->integer('quantite_disponible')->default(0);
            $table->integer('quantite_reservee')->default(0);
            $table->integer('quantite_commandee')->default(0);        // En cours de commande
            $table->string('emplacement')->nullable();                // Rayon magasin
            $table->date('date_dernier_mouvement')->nullable();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices');
            $table->timestamps();

            $table->unique('article_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
