<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La ligne de mini-budget d'une régie est un ENCAISSE, pas une nomenclature :
 * la contrainte unique (regie_avance_id, nomenclature_id) obligeait le 2e
 * décaissement trimestriel à se cumuler sur la ligne du 1er, déjà en pleine
 * consommation — le taux affiché mélangeait alors deux encaisses et ne voulait
 * plus rien dire.
 *
 * Chaque tranche aura sa propre ligne, rattachée à son décaissement, avec son
 * alloué et son taux. L'unicité devient (decaissement_regie_id, nomenclature_id) :
 * partiellement nulle pour l'instant (les lignes héritées n'ont pas encore de
 * tranche), PostgreSQL laisse les NULL se répéter — le temps de l'audit de
 * scission.
 */
return new class extends Migration
{
    private const CONTRAINTE_HERITEE = 'lignes_regies_avances_regie_avance_id_nomenclature_id_unique';
    private const UNIQUE_ENCAISSE = 'lignes_regies_avances_decaissement_nomenclature_unique';

    public function up(): void
    {
        DB::statement('ALTER TABLE lignes_regies_avances
            ADD COLUMN IF NOT EXISTS decaissement_regie_id BIGINT NULL
            REFERENCES decaissements_regies(id) ON DELETE SET NULL');

        // $table->unique() crée une CONTRAINTE, pas un simple index.
        DB::statement('ALTER TABLE lignes_regies_avances
            DROP CONSTRAINT IF EXISTS ' . self::CONTRAINTE_HERITEE);

        DB::statement('CREATE INDEX IF NOT EXISTS lignes_regies_avances_regie_nomenclature_index
            ON lignes_regies_avances (regie_avance_id, nomenclature_id)');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::UNIQUE_ENCAISSE . '
            ON lignes_regies_avances (decaissement_regie_id, nomenclature_id)');
    }

    public function down(): void
    {
        $doublons = DB::select("
            SELECT COUNT(*) AS nb FROM (
                SELECT 1 FROM lignes_regies_avances
                GROUP BY regie_avance_id, nomenclature_id
                HAVING COUNT(*) > 1
            ) q
        ")[0]->nb ?? 0;

        if ((int) $doublons > 0) {
            throw new \RuntimeException(
                "Régression impossible : {$doublons} nomenclature(s) de régie portent plusieurs lignes "
                . "d'encaisse. Recombinez-les avant de rejouer cette migration down()."
            );
        }

        DB::statement('DROP INDEX IF EXISTS ' . self::UNIQUE_ENCAISSE);
        DB::statement('DROP INDEX IF EXISTS lignes_regies_avances_regie_nomenclature_index');
        DB::statement('ALTER TABLE lignes_regies_avances
            ADD CONSTRAINT ' . self::CONTRAINTE_HERITEE . '
            UNIQUE (regie_avance_id, nomenclature_id)');
        DB::statement('ALTER TABLE lignes_regies_avances DROP COLUMN IF EXISTS decaissement_regie_id');
    }
};
