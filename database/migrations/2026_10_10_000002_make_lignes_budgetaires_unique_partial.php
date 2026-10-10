<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LigneBudgetaire utilise SoftDeletes, mais l'index unique (budget_id, nomenclature_id)
 * était total : une ligne retirée continuait d'occuper sa clé, et la recréer — par un
 * mouvement collectif notamment — échouait sur une violation SQL brute.
 * Même correctif que uniq_prevision_nomenclature_active côté recettes.
 */
return new class extends Migration
{
    private const OLD = 'lignes_budgetaires_budget_id_nomenclature_id_unique';
    private const NEW = 'lignes_budgetaires_budget_nomenclature_active';

    public function up(): void
    {
        // L'index old supporte une CONTRAINTE : la drop par contrainte, puis par index
        // au cas où une base ne tiendrait qu'à l'index.
        DB::statement('ALTER TABLE lignes_budgetaires DROP CONSTRAINT IF EXISTS ' . self::OLD);
        DB::statement('DROP INDEX IF EXISTS ' . self::OLD);
        DB::statement('
            CREATE UNIQUE INDEX ' . self::NEW . '
            ON lignes_budgetaires (budget_id, nomenclature_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::NEW);
        DB::statement('
            ALTER TABLE lignes_budgetaires
            ADD CONSTRAINT ' . self::OLD . ' UNIQUE (budget_id, nomenclature_id)
        ');
    }
};
