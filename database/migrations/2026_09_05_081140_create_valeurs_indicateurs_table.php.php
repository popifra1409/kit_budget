<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valeurs_indicateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->string('periode'); // ex: '2026', '2026-T1', '2026-S1'
            $table->decimal('valeur_realisee', 15, 2);
            $table->text('commentaire')->nullable();
            $table->string('statut')->default('saisi'); // saisi, valide, rejete
            $table->foreignId('saisi_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['indicateur_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valeurs_indicateurs');
    }
};
