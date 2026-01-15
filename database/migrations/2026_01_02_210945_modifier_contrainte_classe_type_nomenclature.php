<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Nouvelle contrainte FLEXIBLE pour classe 1
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT check_classe_type 
            CHECK (
                -- Classe 6 : DOIT être depense
                (classe = '6' AND type = 'depense') OR
                
                -- Classe 7 : DOIT être recette
                (classe = '7' AND type = 'recette') OR
                
                -- Classe 1 : FLEXIBLE (recette OU depense)
                (classe = '1' AND type IN ('recette', 'depense')) OR
                
                -- Classe 8 : FLEXIBLE (recette OU autre)
                (classe = '8' AND type IN ('recette', 'depense')) OR
                
                -- Classes 2-5 : depense ou autre
                (classe IN ('2', '3', '4', '5') AND type IN ('depense', 'actif', 'passif', 'autre')) OR
                
                -- Classe 9 : analytique
                (classe = '9' AND type IN ('depense', 'analytique'))
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Remettre l'ancienne contrainte stricte
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT check_classe_type 
            CHECK (
                (classe = '6' AND type = 'depense') OR
                (classe = '7' AND type = 'recette') OR
                (classe IN ('1', '2', '3', '4', '5') AND type IN ('actif', 'passif', 'autre'))
            )
        ");
    }
};
