<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicateurs', function (Blueprint $table) {
            // 'hausse' = une valeur plus elevee est meilleure ; 'baisse' = une valeur plus faible est meilleure
            $table->string('sens', 10)->default('hausse')->after('valeur_cible');
        });
    }

    public function down(): void
    {
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->dropColumn('sens');
        });
    }
};
