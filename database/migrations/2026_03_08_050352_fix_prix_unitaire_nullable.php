<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rendre prix_unitaire nullable (PostgreSQL)
        DB::statement('ALTER TABLE lignes_memoire_depense ALTER COLUMN prix_unitaire DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre NOT NULL (seulement si toutes les valeurs sont non-NULL)
        DB::statement('ALTER TABLE lignes_memoire_depense ALTER COLUMN prix_unitaire SET NOT NULL');
    }
};
