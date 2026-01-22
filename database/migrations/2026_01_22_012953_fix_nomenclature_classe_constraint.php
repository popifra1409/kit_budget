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
        // Supprimer l'ancienne contrainte qui ne permet pas la valeur 1
        DB::statement('
            ALTER TABLE nomenclature_budgetaire 
            DROP CONSTRAINT IF EXISTS nomenclature_budgetaire_classe_check
        ');

        // Recréer la contrainte avec des STRINGS (car la colonne est VARCHAR)
        // Autoriser les valeurs '1' à '9' (avec quotes pour VARCHAR)
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT nomenclature_budgetaire_classe_check 
            CHECK (classe = ANY (ARRAY['1', '2', '3', '4', '5', '6', '7', '8', '9']))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revenir à l'ancienne contrainte (sans la valeur '1')
        DB::statement('
            ALTER TABLE nomenclature_budgetaire 
            DROP CONSTRAINT IF EXISTS nomenclature_budgetaire_classe_check
        ');

        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT nomenclature_budgetaire_classe_check 
            CHECK (classe = ANY (ARRAY['2', '3', '4', '5', '6', '7', '8', '9']))
        ");
    }
};
