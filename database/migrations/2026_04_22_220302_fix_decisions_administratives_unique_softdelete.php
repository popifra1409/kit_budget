<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Supprimer via Schema (contrainte de table)
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropUnique('decisions_administratives_numero_unique');
        });

        // ✅ Recréer comme index partiel avec filtre soft-delete
        \DB::statement('
        CREATE UNIQUE INDEX decisions_administratives_numero_unique
        ON decisions_administratives (numero)
        WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        // ✅ Supprimer l'index partiel
        \DB::statement('DROP INDEX IF EXISTS decisions_administratives_numero_unique');

        // ✅ Recréer la contrainte unique simple (sans filtre)
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->unique('numero', 'decisions_administratives_numero_unique');
        });
    }
};
