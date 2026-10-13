<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Association FACULTATIVE, dans un collectif, entre un mouvement de dépense et le mouvement de
 * recette qui le finance (ex. une subvention nouvelle et les dépenses qu'elle couvre).
 * Une recette peut financer plusieurs dépenses ; une dépense est rattachée à une seule recette.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->foreignId('mouvement_recette_id')->nullable()
                ->constrained('mouvements_collectifs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mouvement_recette_id');
        });
    }
};
