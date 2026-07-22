<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS check_mouvement_ligne');
        DB::statement("ALTER TABLE mouvements_collectifs ADD CONSTRAINT check_mouvement_ligne CHECK (
            (ligne_depense_id IS NOT NULL)
            OR (ligne_recette_id IS NOT NULL)
            OR (nouvelle_ligne_depense_id IS NOT NULL)
            OR (nouvelle_ligne_recette_id IS NOT NULL)
            OR (type = 'virement' AND ligne_source_id IS NOT NULL AND ligne_destination_id IS NOT NULL)
            OR (type = 'virement' AND virement_budgetaire_id IS NOT NULL)
        )");
    }

    public function down(): void {
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS check_mouvement_ligne');
    }
};
