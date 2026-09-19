<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppa_exercices', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('plan_strategique_ep_id')->constrained('plans_strategiques_ep')->restrictOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();

            $table->text('contexte_introduction')->nullable();
            $table->text('performances_anterieures')->nullable();
            $table->text('bilan_technique')->nullable();
            $table->text('bilan_financier')->nullable();

            $table->string('statut')->default('brouillon');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_strategique_ep_id', 'exercice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppa_exercices');
    }
};
