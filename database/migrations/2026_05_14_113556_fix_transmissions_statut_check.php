<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Voir les contraintes actuelles
        // SELECT conname, consrc FROM pg_constraint WHERE conname LIKE '%transmissions%statut%';

        // ✅ Supprimer l'ancienne contrainte
        DB::statement('
            ALTER TABLE transmissions
            DROP CONSTRAINT IF EXISTS transmissions_statut_check
        ');

        // ✅ Recréer avec tous les statuts valides
        DB::statement("
            ALTER TABLE transmissions
            ADD CONSTRAINT transmissions_statut_check
            CHECK (statut IN (
                'en_attente',
                'traite',
                'retourne',
                'rejete',
                'annule'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE transmissions
            DROP CONSTRAINT IF EXISTS transmissions_statut_check
        ');

        // Remettre l'ancienne contrainte sans 'retourne'
        DB::statement("
            ALTER TABLE transmissions
            ADD CONSTRAINT transmissions_statut_check
            CHECK (statut IN (
                'en_attente',
                'traite',
                'rejete',
                'annule'
            ))
        ");
    }
};
