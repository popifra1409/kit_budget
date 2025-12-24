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
        // Supprimer la contrainte check_classe_type
        DB::statement("ALTER TABLE nomenclature_budgetaire DROP CONSTRAINT IF EXISTS check_classe_type");

        // Modifier la colonne classe pour accepter n'importe quel chiffre
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            $table->string('classe', 2)->change();
        });

        // Commentaire
        DB::statement("COMMENT ON COLUMN nomenclature_budgetaire.classe IS 'Classe comptable : peut être n''importe quelle classe (1-9)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre l'enum original et la contrainte
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            DB::statement("ALTER TABLE nomenclature_budgetaire ALTER COLUMN classe TYPE VARCHAR(2)");
        });

        DB::statement("ALTER TABLE nomenclature_budgetaire ADD CONSTRAINT check_classe_type CHECK (
            (classe = '6' AND type = 'depense') OR 
            (classe = '7' AND type = 'recette')
        )");
    }
};
