<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('collectifs_budgetaires');
        Schema::create('collectifs_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained()->onDelete('cascade');
            $table->string('numero')->unique();
            $table->string('libelle');
            $table->date('date_collectif');
            $table->date('date_adoption')->nullable();
            $table->enum('statut', ['projet', 'adopte', 'annule'])->default('projet');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('collectifs_budgetaires');
    }
};
