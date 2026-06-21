<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE expressions_besoins DROP CONSTRAINT IF EXISTS expressions_besoins_statut_check');

        DB::statement("
            ALTER TABLE expressions_besoins
            ADD CONSTRAINT expressions_besoins_statut_check
            CHECK (statut IN ('brouillon', 'soumis', 'valide', 'signe_dg', 'en_commande', 'satisfait', 'rejete', 'annule'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE expressions_besoins DROP CONSTRAINT IF EXISTS expressions_besoins_statut_check');

        DB::statement("
            ALTER TABLE expressions_besoins
            ADD CONSTRAINT expressions_besoins_statut_check
            CHECK (statut IN ('brouillon', 'soumis', 'valide', 'en_commande', 'satisfait', 'rejete', 'annule'))
        ");
    }
};
