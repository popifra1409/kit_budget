<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_engagement', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('code')->unique(); // BC, LC, MARCHE, DECOMPTE_MARCHE, etc.
            $table->string('libelle');
            $table->text('description')->nullable();

            // Seuils de montant
            $table->decimal('montant_min', 15, 2)->nullable();
            $table->decimal('montant_max', 15, 2)->nullable();

            // Règles IR
            $table->enum('mode_calcul_ir', [
                'fixe',              // Taux fixe peu importe le régime
                'selon_regime',      // Taux selon le régime fiscal
                'aucun'              // Pas d'IR
            ])->default('selon_regime');

            $table->decimal('taux_ir_fixe', 5, 2)->nullable(); // Si mode = fixe
            $table->decimal('taux_ir_regime_reel', 5, 2)->nullable();
            $table->decimal('taux_ir_regime_simplifie', 5, 2)->nullable();

            // Métadonnées
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_engagement');
    }
};
