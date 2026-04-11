<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {

            $table->unique(
                ['prevision_recette_id', 'nomenclature_id'],
                'uniq_prevision_nomenclature'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lignes_previsions_recettes', function (Blueprint $table) {

            $table->dropUnique('uniq_prevision_nomenclature');
        });
    }
};
