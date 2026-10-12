<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Supprime le contrôle de chevauchement obsolète de la nomenclature
     * (il référençait date_debut_validite / date_fin_validite, colonnes supprimées).
     *
     * ⚠️ Le déclencheur porte un nom différent selon l'historique des bases :
     *    on supprime tous les noms connus AVANT la fonction (sinon PostgreSQL refuse
     *    de supprimer une fonction encore utilisée par un déclencheur).
     */
    public function up(): void
    {
        foreach (['check_nomenclature_overlap', 'trigger_check_no_overlap_nomenclature', 'check_no_overlap_nomenclature'] as $declencheur) {
            DB::statement("DROP TRIGGER IF EXISTS {$declencheur} ON nomenclature_budgetaire");
        }

        DB::statement('DROP FUNCTION IF EXISTS check_no_overlap_nomenclature()');
    }

    public function down(): void
    {
        // Pas de recréation : le contrôle utilisait des colonnes qui n'existent plus.
    }
};
