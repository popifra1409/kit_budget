<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('projets_strategiques'); // FK vers actions_sous_programmes, a dropper en 1er
        Schema::dropIfExists('actions_sous_programmes');
    }

    public function down(): void
    {
        // Pas de recreation automatique - tables vides, non critiques.
        // Si besoin de revenir en arriere, restaurer depuis les migrations d'origine.
    }
};