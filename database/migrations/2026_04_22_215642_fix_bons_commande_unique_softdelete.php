<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Supprimer l'ancienne contrainte
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropUnique('bons_commande_numero_unique');
        });

        // ✅ Index partiel — unique seulement sur les non-supprimés
        \DB::statement('
        CREATE UNIQUE INDEX bons_commande_numero_unique
        ON bons_commande (numero)
        WHERE deleted_at IS NULL
    ');
    }

    public function down(): void
    {
        \DB::statement('DROP INDEX IF EXISTS bons_commande_numero_unique');
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->unique('numero');
        });
    }
};
