<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Ajouter une nouvelle contrainte plus permissive
        // Option A: Permettre toutes les combinaisons (pas de contrainte)
        // (Ne rien faire)

        // Option B: Contrainte adaptée selon vos besoins
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

    public function down(): void
    {
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type');

        // Remettre l'ancienne contrainte (à adapter selon votre contrainte actuelle)
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT check_classe_type 
            CHECK (
                (classe = '6' AND type = 'depense') OR
                (classe = '7' AND type = 'recette')
            )
        ");
    }
};
