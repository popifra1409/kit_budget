<?php
// app/Support/ConservationJournal.php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les deux regles de conservation du journal, gardees au meme endroit.
 *
 * Le charter les enoncait de memoire et s'est mis a mentir (« 90 jours », alors que la
 * colonne valait 365 depuis l'archivage a six mois). Des qu'une regle de retention est
 * enoncee a la fois par une commande et par un document, elle a besoin d'une source
 * unique : ici c'est cette classe.
 */
class ConservationJournal
{
    /** Age, en mois, a partir duquel une entree a le droit d'etre archivee. */
    public static function ageArchivage(?int $mois = null): int
    {
        return $mois ?: (int) config('audit.conservation.archivage_mois', 6);
    }

    /**
     * Duree de conservation, en jours, au-dela de laquelle une entree peut etre supprimee :
     * l'option posee sur la commande si elle existe, sinon la plus longue des durees saisies
     * par les etablissements, sinon le plancher du fichier de config.
     *
     * « la plus longue », et non la ligne 1 : parametres_structure porte une ligne par
     * etablissement alors que le journal, lui, est unique — la regle la plus protectrice des
     * preuves doit l'emporter sur un simple ordre de creation.
     */
    public static function dureePurge(?int $jours = null): int
    {
        if ($jours) {
            return $jours;
        }

        $saisies = Schema::hasColumn('parametres_structure', 'duree_conservation_logs')
            ? DB::table('parametres_structure')->pluck('duree_conservation_logs')->filter()->all()
            : [];

        return $saisies === []
            ? (int) config('audit.conservation.purge_jours', 365)
            : (int) max($saisies);
    }

    /** Date avant laquelle une entree a l'age d'etre archivee. */
    public static function seuilArchivage(?int $mois = null): Carbon
    {
        return Carbon::now()->subMonths(static::ageArchivage($mois));
    }

    /** Date avant laquelle une entree peut etre supprimee. */
    public static function seuilPurge(?int $jours = null): Carbon
    {
        return Carbon::now()->subDays(static::dureePurge($jours));
    }
}
