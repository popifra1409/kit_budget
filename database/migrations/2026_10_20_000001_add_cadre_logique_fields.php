<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Responsable de mise en œuvre d'une action, comme pour les activités
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // « Source de vérification » exigée par le cadre logique (Tableau 1 et matrices)
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->text('source_verification')->nullable();
        });

        // Cible par année de la matrice (N+1, N+2, N+3) : une ligne par période
        Schema::table('valeurs_indicateurs', function (Blueprint $table) {
            $table->decimal('valeur_cible', 15, 2)->nullable();
        });
        DB::statement('ALTER TABLE valeurs_indicateurs ALTER COLUMN valeur_realisee DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->dropColumn('source_verification');
        });
        Schema::table('valeurs_indicateurs', function (Blueprint $table) {
            $table->dropColumn('valeur_cible');
        });
        DB::statement('ALTER TABLE valeurs_indicateurs ALTER COLUMN valeur_realisee SET NOT NULL');
    }
};
