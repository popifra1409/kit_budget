<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites_mesure', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');           // Ex: Boîte, Plaquette, Comprimé
            $table->string('symbole', 20)->nullable(); // Ex: bte, plq, cpr
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites_mesure');
    }
};
