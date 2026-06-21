<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_article', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('code', 30)->nullable();
            // ✅ Détermine si le conditionnement est requis pour cette catégorie
            $table->boolean('est_pharmacie')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_article');
    }
};
