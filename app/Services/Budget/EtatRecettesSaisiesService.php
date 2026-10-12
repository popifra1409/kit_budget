<?php

namespace App\Services\Budget;

use App\Models\Exercice;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\RecetteReelle;
use Illuminate\Support\Collection;

/**
 * État des recettes réelles SAISIES, pour le contrôle de la saisie (pointage avec les pièces) :
 * regroupement par ligne de recettes, par débiteur ou chronologique ; totaux attendu / encaissé / RAR ;
 * contrôle du prévu du mois face à l'encaissé saisi, par ligne.
 */
class EtatRecettesSaisiesService
{
    public const MOIS = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

    public const REGROUPEMENTS = ['ligne' => 'Par ligne de recettes', 'debiteur' => 'Par débiteur / payeur', 'chronologique' => 'Chronologique'];

    /**
     * @param array|null $mois  mois de l'exercice (1..12) ; null = tous
     * @param array|null $ids   recettes précises (impression d'une sélection) ; prioritaire sur les autres filtres
     */
    public function construire(?int $exerciceId, ?array $mois = null, ?string $du = null, ?string $au = null,
                               string $regroupement = 'ligne', ?int $tiersId = null, ?array $ids = null): array
    {
        $recettes = RecetteReelle::query()
            ->with(['tiers', 'previsionRecetteMensuelle.lignePrevisionRecette'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when(!$ids && $exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->when(!$ids && $mois, fn ($q) => $q->whereIn('mois', $mois))
            ->when(!$ids && $du, fn ($q) => $q->whereDate('date_recette', '>=', $du))
            ->when(!$ids && $au, fn ($q) => $q->whereDate('date_recette', '<=', $au))
            ->when(!$ids && $tiersId, fn ($q) => $q->where('tiers_recette_id', $tiersId))
            ->where('statut', '!=', 'prevue')
            ->orderBy('mois')->orderBy('date_recette')->orderBy('numero')
            ->get()
            ->map(fn ($r) => [
                'numero'     => $r->numero,
                'date'       => $r->date_recette,
                'mois'       => (int) $r->mois,
                'code'       => $r->code_nomenclature,
                'ligne'      => $r->previsionRecetteMensuelle?->lignePrevisionRecette?->libelle_nomenclature ?? $r->libelle,
                'ligne_id'   => $r->previsionRecetteMensuelle?->ligne_prevision_recette_id,
                'debiteur'   => $r->tiers?->nom ?? $r->payeur ?? '—',
                'attendu'    => (float) ($r->montant_constate ?? $r->montant),
                'encaisse'   => (float) $r->montant,
                'rar'        => (float) $r->reste_a_recouvrer,
                'mode'       => $r->mode_paiement,
                'reference'  => $r->reference_paiement,
                'statut'     => RecetteReelle::STATUTS[$r->statut] ?? $r->statut,
                'observations' => $r->observations,
            ]);

        $groupes = match ($regroupement) {
            'debiteur'      => $recettes->groupBy('debiteur')->sortKeys(),
            'chronologique' => $recettes->groupBy(fn ($r) => self::MOIS[$r['mois']] ?? $r['mois']),
            default         => $recettes->groupBy(fn ($r) => "{$r['code']} — {$r['ligne']}")->sortKeys(),
        };

        return [
            'exercice'     => $exerciceId ? Exercice::find($exerciceId) : null,
            'mois'         => $mois,
            'periode'      => [$du, $au],
            'regroupement' => self::REGROUPEMENTS[$regroupement] ?? $regroupement,
            'selection'    => (bool) $ids,
            'groupes'      => $groupes->map(fn (Collection $g) => [
                'recettes' => $g->values(),
                'nombre'   => $g->count(),
                'attendu'  => (float) $g->sum('attendu'),
                'encaisse' => (float) $g->sum('encaisse'),
                'rar'      => (float) $g->sum('rar'),
            ]),
            'totaux' => [
                'nombre'   => $recettes->count(),
                'attendu'  => (float) $recettes->sum('attendu'),
                'encaisse' => (float) $recettes->sum('encaisse'),
                'rar'      => (float) $recettes->sum('rar'),
            ],
            'controle' => $this->controlePrevu($recettes, $exerciceId, $mois),
        ];
    }

    /** Par ligne de recettes : prévu des mois retenus face à l'encaissé saisi. */
    protected function controlePrevu(Collection $recettes, ?int $exerciceId, ?array $mois): Collection
    {
        if (!$exerciceId) {
            return collect();
        }

        $prevus = PrevisionRecetteMensuelle::with('lignePrevisionRecette')
            ->where('exercice_id', $exerciceId)
            ->when($mois, fn ($q) => $q->whereIn('mois', $mois))
            ->get()
            ->groupBy('ligne_prevision_recette_id');

        return $prevus->map(function ($m, $ligneId) use ($recettes) {
            $l = $m->first()->lignePrevisionRecette;
            $saisi = (float) $recettes->where('ligne_id', $ligneId)->sum('encaisse');
            $prevu = (float) $m->sum('montant_prevu');

            return [
                'code' => $l?->code_nomenclature, 'libelle' => $l?->libelle_nomenclature,
                'prevu' => $prevu, 'encaisse' => $saisi, 'ecart' => $saisi - $prevu,
                'taux' => $prevu > 0 ? round($saisi / $prevu * 100, 1) : null,
                'nombre' => $recettes->where('ligne_id', $ligneId)->count(),
            ];
        })
            ->filter(fn ($c) => $c['prevu'] > 0 || $c['encaisse'] > 0)
            ->sortBy('code')->values();
    }
}