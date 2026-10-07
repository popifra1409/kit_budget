<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\LigneBudgetaire;
use App\Models\Tache;
use Illuminate\Support\Collection;

/**
 * Concordance permanente : Σ CP (et AE) des sous-tâches d'un compte = dotation actualisée de la ligne.
 *
 * Les collectifs et virements modifient les LIGNES ; ce service répercute la différence sur les
 * SOUS-TÂCHES du même compte (même exercice) :
 *  - une seule sous-tâche : elle reçoit toute la différence ;
 *  - plusieurs            : répartition au prorata de leurs CP (le reliquat d'arrondi sur la plus grosse) ;
 *  - CP tous nuls         : répartition égale.
 * La différence s'applique à la fois aux AE et aux CP (un collectif ouvre des AE et des CP).
 */
class SynchronisationTachesService
{
    /** Vrai pendant une synchronisation : autorise la modification des sous-tâches d'un budget adopté. */
    public static bool $enCours = false;

    /** Sous-tâches portant le compte de la ligne, pour l'exercice du budget. */
    public function sousTaches(LigneBudgetaire $ligne): Collection
    {
        $code = $ligne->nomenclature?->code;
        $exerciceId = $ligne->budget?->exercice_id;

        if (!$code || !$exerciceId) {
            return collect();
        }

        return Tache::withoutGlobalScope('exercice')
            ->where('exercice_id', $exerciceId)
            ->where('niveau', 'sous_tache')
            ->whereHas('nomenclature', fn($q) => $q->where('code', $code))
            ->orderBy('id')
            ->get();
    }

    /**
     * Calcule (et applique si demandé) la répartition de l'écart.
     * @return array{code: ?string, dotation: float, cp_taches: float, ecart: float, repartition: array}
     */
    public function synchroniser(LigneBudgetaire $ligne, bool $appliquer = true, string $origine = 'automatique'): array
    {
        $ligne->loadMissing(['nomenclature', 'budget']);
        $taches = $this->sousTaches($ligne);
        $dotation = round((float) $ligne->getBudgetRectifieReel(), 2);
        $cpTaches = round((float) $taches->sum('cp'), 2);
        $ecart = round($dotation - $cpTaches, 2);

        $resultat = ['code' => $ligne->nomenclature?->code, 'dotation' => $dotation, 'cp_taches' => $cpTaches, 'ecart' => $ecart, 'repartition' => []];

        if (abs($ecart) < 1 || $taches->isEmpty()) {
            return $resultat;
        }

        // Parts : prorata des CP, sinon égales ; reliquat d'arrondi sur la plus grosse sous-tâche
        $base = $cpTaches > 0 ? $cpTaches : (float) $taches->count();
        $parts = $taches->mapWithKeys(fn($t) => [$t->id => round($ecart * (($cpTaches > 0 ? (float) $t->cp : 1) / $base), 0)]);
        $reliquat = round($ecart - $parts->sum(), 2);
        $plusGrosse = $taches->sortByDesc(fn($t) => (float) $t->cp)->first()->id;
        $parts[$plusGrosse] = $parts[$plusGrosse] + $reliquat;

        foreach ($taches as $t) {
            $part = (float) $parts[$t->id];
            $resultat['repartition'][] = [
                'tache' => $t->code,
                'cp_avant' => (float) $t->cp,
                'cp_apres' => (float) $t->cp + $part,
                'ae_avant' => (float) $t->ae,
                'ae_apres' => (float) $t->ae + $part,
                'part' => $part,
            ];
        }

        if ($appliquer) {
            static::$enCours = true;
            try {
                foreach ($taches as $t) {
                    $part = (float) $parts[$t->id];
                    $t->cp = max(0, (float) $t->cp + $part);
                    $t->ae = max(0, (float) $t->ae + $part);
                    $t->save();   // l'événement « saved » recalcule la tâche parente
                }
            } finally {
                static::$enCours = false;
            }

            ActivityLog::logAction($ligne, 'synchronisation_taches', [
                'compte' => $resultat['code'],
                'dotation' => $dotation,
                'ecart' => $ecart,
                'sous_taches' => count($resultat['repartition']),
                'origine' => $origine,
            ]);
        }

        return $resultat;
    }
}
