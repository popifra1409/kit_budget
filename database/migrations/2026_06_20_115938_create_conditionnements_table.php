<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditionnements', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');  // Ex: Plaquette de 10 comprimés, Flacon 100ml, Gel 30g
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conditionnements');
    }
};
