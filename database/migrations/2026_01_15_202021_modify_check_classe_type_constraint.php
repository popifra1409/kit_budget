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
        // Supprimer l'ancienne contrainte
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Créer la nouvelle contrainte flexible
        // ATTENTION : classe est VARCHAR, donc utiliser des guillemets '1', '2', etc.
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT check_classe_type CHECK (
                (classe IN ('1', '2', '3', '4', '5', '6') AND type = 'depense') OR
                (classe IN ('1', '7', '8') AND type = 'recette')
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la contrainte flexible
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Remettre l'ancienne contrainte stricte
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT check_classe_type CHECK (
                (classe IN ('1', '2', '3', '4', '5', '6') AND type = 'depense') OR
                (classe IN ('7', '8') AND type = 'recette')
            )
        ");
    }
};
