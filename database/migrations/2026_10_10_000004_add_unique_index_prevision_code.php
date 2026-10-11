<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un code de nomenclature ne peut apparaître qu'une fois par prévision de recettes :
 * l'index existant uniq_prevision_nomenclature_active porte sur nomenclature_id et
 * laisse donc passer le doublon né d'un « 731207 » enregistré deux fois en nomenclature
 * (mouvement collectif supprimé puis recréé). Les doublons hérités doivent être retirés AVANT :
 *
 *   php artisan budget:dedup-lignes-recettes        -- aperçu
 *   php artisan budget:dedup-lignes-recettes --run  -- ou database/sql/dedup_lignes_recettes.sql
 *
 * L'index est partiel (deleted_at IS NULL) : une ligne mise en corbeille continue de
 * porter les recouvrements déjà enregistrés sur ses 12 mois.
 */
return new class extends Migration
{
    private const INDEX = 'lignes_prev_recettes_prevision_code_active';

    public function up(): void
    {
        $doublons = DB::select("
            SELECT COUNT(*) as nb FROM (
                SELECT 1 FROM lignes_previsions_recettes
                WHERE deleted_at IS NULL AND code_nomenclature IS NOT NULL
                GROUP BY prevision_recette_id, code_nomenclature
                HAVING COUNT(*) > 1
            ) q
        ")[0]->nb ?? 0;

        if ((int) $doublons > 0) {
            throw new \RuntimeException(
                "Migration interrompue : {$doublons} code(s) sont doublonnés dans une même prévision de recettes. "
                . 'Nettoyez-les d\'abord avec « php artisan budget:dedup-lignes-recettes --run » '
                . '(aperçu sans --run, ou database/sql/dedup_lignes_recettes.sql sur une base sans artisan), '
                . 'puis relancez migrate.'
            );
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX . '
            ON lignes_previsions_recettes (prevision_recette_id, code_nomenclature)
            WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
    }
};
