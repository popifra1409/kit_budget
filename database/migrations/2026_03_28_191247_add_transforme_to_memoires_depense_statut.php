<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \DB::statement("
        ALTER TABLE memoires_depense
        DROP CONSTRAINT memoires_depense_statut_check
    ");

        \DB::statement("
        ALTER TABLE memoires_depense
        ADD CONSTRAINT memoires_depense_statut_check
        CHECK (statut IN ('brouillon', 'valide', 'transmis', 'transforme', 'annule'))
    ");
    }

    public function down(): void
    {
        \DB::statement("
        ALTER TABLE memoires_depense
        DROP CONSTRAINT memoires_depense_statut_check
    ");

        \DB::statement("
        ALTER TABLE memoires_depense
        ADD CONSTRAINT memoires_depense_statut_check
        CHECK (statut IN ('brouillon', 'valide', 'transmis', 'annule'))
    ");
    }
};
