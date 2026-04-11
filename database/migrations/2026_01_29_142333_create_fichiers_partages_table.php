<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichiers_partages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');

            // Fichier
            $table->string('nom_fichier');
            $table->string('chemin_fichier');
            $table->string('type_mime')->nullable();
            $table->bigInteger('taille')->nullable(); // en octets

            // Téléchargements
            $table->json('telecharge_par')->nullable(); // Array des user_ids

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichiers_partages');
    }
};
