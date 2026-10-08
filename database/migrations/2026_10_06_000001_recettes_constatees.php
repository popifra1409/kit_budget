<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recettes CONSTATÉES : créance certaine enregistrée avant son encaissement
 * (prise en charge d'assuré, convention, subvention notifiée…). Non comptée dans le recouvré.
 * Alimente le RAR (recettes certaines à recouvrer) de la clôture d'exercice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recettes_reelles', function (Blueprint $table) {
            if (!Schema::hasColumn('recettes_reelles', 'date_constatation')) {
                $table->date('date_constatation')->nullable();
            }
            if (!Schema::hasColumn('recettes_reelles', 'date_encaissement')) {
                $table->date('date_encaissement')->nullable();
            }
        });

        // Si une contrainte CHECK limite les statuts (PostgreSQL), on y ajoute « constatee »
        // en conservant toutes les valeurs déjà autorisées.
        if (DB::getDriverName() === 'pgsql') {
            $c = DB::selectOne(
                "SELECT c.conname AS nom, pg_get_constraintdef(c.oid) AS definition
                   FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid
                  WHERE t.relname = 'recettes_reelles' AND c.contype = 'c'
                    AND pg_get_constraintdef(c.oid) LIKE '%statut%'"
            );

            if ($c) {
                preg_match_all("/'([^']+)'::/", $c->definition, $m);
                $valeurs = array_values(array_unique(array_merge($m[1] ?? [], ['prevue', 'constatee', 'encaissee', 'comptabilisee', 'validee'])));
                $liste = implode(', ', array_map(fn($v) => DB::getPdo()->quote($v), $valeurs));

                DB::statement("ALTER TABLE recettes_reelles DROP CONSTRAINT \"{$c->nom}\"");
                DB::statement("ALTER TABLE recettes_reelles ADD CONSTRAINT \"{$c->nom}\" CHECK ((statut)::text = ANY (ARRAY[{$liste}]::text[]))");
            }
        }
    }

    public function down(): void
    {
        Schema::table('recettes_reelles', function (Blueprint $table) {
            $table->dropColumn(['date_constatation', 'date_encaissement']);
        });
    }
};
