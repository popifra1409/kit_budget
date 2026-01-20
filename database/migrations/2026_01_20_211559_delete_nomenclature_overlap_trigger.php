<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supprimer le trigger obsolète qui référence date_debut_validite et date_fin_validite
        DB::statement('DROP TRIGGER IF EXISTS check_nomenclature_overlap ON nomenclature_budgetaire;');

        // Supprimer la fonction associée
        DB::statement('DROP FUNCTION IF EXISTS check_no_overlap_nomenclature();');

        // Note: Si vous avez besoin de cette validation, recréez le trigger avec les nouveaux noms de colonnes
        // Voir la section "Recréation du trigger" ci-dessous si nécessaire
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // On ne recrée pas le trigger en rollback car il utilisait des colonnes qui n'existent plus
        // Si rollback nécessaire, il faudra d'abord recréer les colonnes
    }
};
