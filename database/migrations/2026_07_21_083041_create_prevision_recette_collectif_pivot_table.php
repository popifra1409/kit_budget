<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('prevision_recette_collectif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prevision_recette_id')->constrained('previsions_recettes')->cascadeOnDelete();
            $table->foreignId('collectif_budgetaire_id')->constrained('collectifs_budgetaires')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('prevision_recette_collectif');
    }
};
