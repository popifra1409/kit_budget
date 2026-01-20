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
        Schema::create('previsions_recettes', function (Blueprint $table) {
            $table->id();

            // Références
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();

            // Identification
            $table->string('code', 50)->unique()->comment('Code unique de la prévision');
            $table->string('libelle', 255)->comment('Libellé de la prévision de recettes');
            $table->integer('exercice')->comment('Année de l\'exercice');

            // Dates
            $table->date('date_adoption')->nullable()->comment('Date d\'adoption');
            $table->date('date_revision')->nullable()->comment('Date de dernière révision');

            // Statut
            $table->enum('statut', [
                'elaboration',
                'adopte',
                'execution',
                'cloture'
            ])->default('elaboration');

            // État
            $table->boolean('actif')->default(true);

            // Observations
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('exercice_id');
            $table->index('exercice');
            $table->index('statut');
            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('previsions_recettes');
    }
};
