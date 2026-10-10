<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le code de nomenclature est la clé métier d'un exercice : il ne peut pas être
 * doublonné deux fois pour le même type. Les doublons hérités (collectifs « créer
 * une nouvelle ligne » supprimés puis repris) doivent être fusionnés AVANT :
 *
 *   php artisan budget:dedup-nomenclature            -- aperçu
 *   php artisan budget:dedup-nomenclature --run      -- ou database/sql/dedup_nomenclature.sql
 *
 * L'index est partiel (deleted_at IS NULL) : les nomenclatures en corbeille
 * restent référencées par les documents et mouvements déjà enregistrés.
 */
return new class extends Migration
{
    private const INDEX = 'nomenclature_exercice_code_type_active';

    public function up(): void
    {
        $doublons = DB::select("
            SELECT COUNT(*) as nb FROM (
                SELECT 1 FROM nomenclature_budgetaire
                WHERE deleted_at IS NULL AND exercice_id IS NOT NULL
                GROUP BY exercice_id, code, type
                HAVING COUNT(*) > 1
            ) q
        ")[0]->nb ?? 0;

        if ((int) $doublons > 0) {
            throw new \RuntimeException(
                "Migration interrompue : {$doublons} code(s) de nomenclature sont doublonnés dans un même exercice. "
                . 'Fusionnez-les d\'abord avec « php artisan budget:dedup-nomenclature --run » '
                . '(aperçu sans --run, ou database/sql/dedup_nomenclature.sql sur une base sans artisan), '
                . 'puis relancez migrate.'
            );
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX . '
            ON nomenclature_budgetaire (exercice_id, code, type)
            WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
    }
};
