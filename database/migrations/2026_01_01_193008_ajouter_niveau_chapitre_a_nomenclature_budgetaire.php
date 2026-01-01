<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS nomenclature_budgetaire_niveau_check');

        // Ajouter la nouvelle contrainte avec "chapitre"
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT nomenclature_budgetaire_niveau_check 
            CHECK (niveau::text = ANY (ARRAY['chapitre'::character varying, 'classe'::character varying, 'compte'::character varying, 'sous_compte'::character varying, 'ligne'::character varying]::text[]))
        ");
    }

    public function down(): void
    {
        // Supprimer la nouvelle contrainte
        DB::statement('ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS nomenclature_budgetaire_niveau_check');

        // Remettre l'ancienne contrainte (sans "chapitre")
        DB::statement("
            ALTER TABLE nomenclature_budgetaire 
            ADD CONSTRAINT nomenclature_budgetaire_niveau_check 
            CHECK (niveau::text = ANY (ARRAY['classe'::character varying, 'compte'::character varying, 'sous_compte'::character varying, 'ligne'::character varying]::text[]))
        ");
    }
};
