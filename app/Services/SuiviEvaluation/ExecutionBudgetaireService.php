<?php

namespace App\Services\SuiviEvaluation;

use App\Models\LigneBudgetaire;
use App\Models\Tache;
use Carbon\Carbon;
use InvalidArgumentException;

class ExecutionBudgetaireService
{
    /** Cache des totaux AE par nomenclature/exercice pour le calcul du prorata. */
    protected array $totauxAeParNomenclature = [];

    /**
     * Convertit '2026-03' (mensuel) ou '2026-T1' (trimestriel) en bornes de dates.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bornesPeriode(string $periode, string $type): array
    {
        if ($type === 'mensuel' && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $periode, $m)) {
            $debut = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
            return [$debut, $debut->copy()->endOfMonth()];
        }

        if ($type === 'trimestriel' && preg_match('/^(\d{4})-T([1-4])$/', $periode, $m)) {
            $debut = Carbon::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1)->startOfDay();
            return [$debut, $debut->copy()->addMonths(2)->endOfMonth()];
        }

        $format = $type === 'mensuel' ? 'AAAA-MM (ex: 2026-03)' : 'AAAA-Tn (ex: 2026-T1)';
        throw new InvalidArgumentException("Période « {$periode} » invalide : format attendu {$format}.");
    }

    /**
     * Montant engage sur une ligne budgetaire entre deux dates.
     *
     * @return array{montant: float, source: string}
     */
    public function montantEngage(LigneBudgetaire $ligne, Carbon $debut, Carbon $fin): array
    {
        $cfg = config('suivi_evaluation.engagements');

        if (!($cfg['enabled'] ?? false)) {
            // Repli : cumul annuel a l'instant T (ne tient pas compte des bornes)
            return ['montant' => (float) $ligne->engage, 'source' => 'cumul_annuel'];
        }

        $query = $cfg['model']::query()
            ->where($cfg['ligne_fk'], $ligne->id)
            ->whereBetween($cfg['date'], [$debut, $fin]);

        if (!empty($cfg['statut'])) {
            $query->whereIn($cfg['statut'], $cfg['statuts_retenus']);
        }

        return ['montant' => (float) $query->sum($cfg['montant']), 'source' => 'engagements_dates'];
    }

    /**
     * Part de la ligne budgetaire revenant a cette sous-tache, au prorata des AE,
     * quand plusieurs sous-taches du meme exercice partagent la meme nomenclature.
     */
    public function quotePart(Tache $tache, int $exerciceId): float
    {
        if (!$tache->nomenclature_id) {
            return 0.0;
        }

        $cle = "{$tache->nomenclature_id}:{$exerciceId}";

        $this->totauxAeParNomenclature[$cle] ??= (float) Tache::query()
            ->where('niveau', 'sous_tache')
            ->where('nomenclature_id', $tache->nomenclature_id)
            ->whereHas('activite', fn($q) => $q->where('exercice_id', $exerciceId))
            ->sum('ae');

        $total = $this->totauxAeParNomenclature[$cle];

        // Aucune AE renseignee : on ne peut pas repartir, on attribue tout (cas ligne non partagee)
        return $total > 0 ? round((float) $tache->ae / $total, 4) : 1.0;
    }
}
