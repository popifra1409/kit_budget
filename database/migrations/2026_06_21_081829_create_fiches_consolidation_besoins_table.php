<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_consolidation_besoins', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->date('date_consolidation')->default(now());
            $table->string('statut')->default('ouverte'); // ouverte | cloturee
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiches_consolidation_besoins');
    }
};
