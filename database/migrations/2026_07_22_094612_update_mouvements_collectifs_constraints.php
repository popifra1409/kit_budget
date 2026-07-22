<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── 1. Ajouter les colonnes virement si absentes ─────────
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            if (!Schema::hasColumn('mouvements_collectifs', 'ligne_source_id')) {
                $table->unsignedBigInteger('ligne_source_id')->nullable();
            }
            if (!Schema::hasColumn('mouvements_collectifs', 'ligne_destination_id')) {
                $table->unsignedBigInteger('ligne_destination_id')->nullable();
            }
        });

        // ── 2. Corriger contrainte type (ajouter 'virement') ─────
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS mouvements_collectifs_type_check');
        DB::statement("ALTER TABLE mouvements_collectifs ADD CONSTRAINT mouvements_collectifs_type_check
            CHECK (type IN ('depense', 'recette', 'virement'))");

        // ── 3. Corriger contrainte check_mouvement_ligne ──────────
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS check_mouvement_ligne');
        DB::statement("ALTER TABLE mouvements_collectifs ADD CONSTRAINT check_mouvement_ligne CHECK (
            (ligne_depense_id IS NOT NULL)
            OR (ligne_recette_id IS NOT NULL)
            OR (nouvelle_ligne_depense_id IS NOT NULL)
            OR (nouvelle_ligne_recette_id IS NOT NULL)
            OR (type = 'virement' AND ligne_source_id IS NOT NULL AND ligne_destination_id IS NOT NULL)
        )");
    }

    public function down(): void
    {
        // Revenir à l'état initial
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS mouvements_collectifs_type_check');
        DB::statement("ALTER TABLE mouvements_collectifs ADD CONSTRAINT mouvements_collectifs_type_check
            CHECK (type IN ('depense', 'recette'))");

        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS check_mouvement_ligne');
        DB::statement("ALTER TABLE mouvements_collectifs ADD CONSTRAINT check_mouvement_ligne CHECK (
            (ligne_depense_id IS NOT NULL)
            OR (ligne_recette_id IS NOT NULL)
            OR (nouvelle_ligne_depense_id IS NOT NULL)
            OR (nouvelle_ligne_recette_id IS NOT NULL)
        )");

        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropColumn(['ligne_source_id', 'ligne_destination_id']);
        });
    }
};
