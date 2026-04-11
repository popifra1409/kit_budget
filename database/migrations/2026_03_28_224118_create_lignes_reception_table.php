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
        Schema::create('lignes_reception', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_id')->constrained()->onDelete('cascade');
            $table->foreignId('article_id')->constrained();
            $table->integer('quantite_commandee');
            $table->integer('quantite_recue');
            $table->integer('quantite_conforme')->default(0);
            $table->integer('quantite_rejetee')->default(0);
            $table->decimal('prix_unitaire', 15, 2)->default(0);
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->text('observations')->nullable();
            $table->boolean('conforme')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_reception');
    }
};
