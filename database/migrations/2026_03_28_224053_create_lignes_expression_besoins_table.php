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
        Schema::create('lignes_expression_besoins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_besoin_id')->constrained('expressions_besoins')->onDelete('cascade');
            $table->foreignId('article_id')->constrained();
            $table->integer('quantite_demandee');
            $table->integer('quantite_en_stock')->default(0);         // Situation stock au moment
            $table->integer('quantite_a_commander')->default(0);      // Déduite après situation stock
            $table->text('justification')->nullable();
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_expression_besoins');
    }
};
