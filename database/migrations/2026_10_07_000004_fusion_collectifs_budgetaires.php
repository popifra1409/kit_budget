<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusion de collectifs budgétaires en projet :
 *  - collectifs d'origine conservés au statut « fusionne », liés au collectif issu de la fusion ;
 *  - chaque mouvement garde la trace de son collectif d'origine (pour pouvoir défaire la fusion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collectifs_budgetaires', function (Blueprint $table) {
            $table->foreignId('fusionne_dans_id')->nullable()->constrained('collectifs_budgetaires')->nullOnDelete();
            $table->date('date_fusion')->nullable();
            $table->json('fusion_meta')->nullable();   // collectifs d'origine, regroupements effectués
        });

        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->foreignId('collectif_origine_id')->nullable()->constrained('collectifs_budgetaires')->nullOnDelete();
        });

        // Statut « fusionne » : élargit une éventuelle contrainte CHECK sans retirer les valeurs existantes
        if (DB::getDriverName() === 'pgsql') {
            $c = DB::selectOne(
                "SELECT c.conname AS nom, pg_get_constraintdef(c.oid) AS definition
                   FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid
                  WHERE t.relname = 'collectifs_budgetaires' AND c.contype = 'c'
                    AND pg_get_constraintdef(c.oid) LIKE '%statut%'"
            );

            if ($c) {
                preg_match_all("/'([^']+)'::/", $c->definition, $m);
                $valeurs = array_values(array_unique(array_merge($m[1] ?? [], ['projet', 'adopte', 'annule', 'fusionne'])));
                $liste = implode(', ', array_map(fn($v) => DB::getPdo()->quote($v), $valeurs));

                DB::statement("ALTER TABLE collectifs_budgetaires DROP CONSTRAINT \"{$c->nom}\"");
                DB::statement("ALTER TABLE collectifs_budgetaires ADD CONSTRAINT \"{$c->nom}\" CHECK ((statut)::text = ANY (ARRAY[{$liste}]::text[]))");
            }
        }
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collectif_origine_id');
        });
        Schema::table('collectifs_budgetaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fusionne_dans_id');
            $table->dropColumn(['date_fusion', 'fusion_meta']);
        });
    }
};
