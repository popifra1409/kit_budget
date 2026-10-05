<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow des mouvements de crédits : rejet motivé, récupération, annulation (statut « annule »),
 * annulation de l'exécution. Traçabilité des motifs et des auteurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virements_budgetaires', function (Blueprint $table) {
            $table->text('motif_rejet')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_annulation')->nullable();
            $table->text('motif_annulation')->nullable();
        });

        // Si une contrainte CHECK limite les statuts (PostgreSQL), on l'élargit à « annule »
        // en conservant toutes les valeurs déjà autorisées.
        if (DB::getDriverName() === 'pgsql') {
            $c = DB::selectOne(
                "SELECT c.conname AS nom, pg_get_constraintdef(c.oid) AS definition
                   FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid
                  WHERE t.relname = 'virements_budgetaires' AND c.contype = 'c'
                    AND pg_get_constraintdef(c.oid) LIKE '%statut%'"
            );

            if ($c) {
                preg_match_all("/'([^']+)'::/", $c->definition, $m);
                $valeurs = array_values(array_unique(array_merge($m[1] ?? [], ['en_attente', 'approuve', 'execute', 'rejete', 'annule'])));
                $liste = implode(', ', array_map(fn($v) => DB::getPdo()->quote($v), $valeurs));

                DB::statement("ALTER TABLE virements_budgetaires DROP CONSTRAINT \"{$c->nom}\"");
                DB::statement("ALTER TABLE virements_budgetaires ADD CONSTRAINT \"{$c->nom}\" CHECK ((statut)::text = ANY (ARRAY[{$liste}]::text[]))");
            }
        }
    }

    public function down(): void
    {
        Schema::table('virements_budgetaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['motif_rejet', 'date_annulation', 'motif_annulation']);
        });
    }
};
