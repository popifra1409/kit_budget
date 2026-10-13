<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\CollectifBudgetaire;
use App\Models\MouvementCollectif;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Association facultative recette → dépenses dans un collectif, pour lire l'équilibre par financement.
 * Purement descriptive : aucun effet sur les montants ni sur l'application du collectif.
 */
class FinancementCollectifService
{
    /** Mouvements actifs d'un type, avec leur ligne et leur libellé. */
    public function mouvements(CollectifBudgetaire $collectif, string $type): Collection
    {
        return MouvementCollectif::where('collectif_budgetaire_id', $collectif->id)
            ->where('type', $type)
            ->whereNull('date_annulation')
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->with($type === 'recette' ? ['ligneRecette', 'nouvelleLigneRecette'] : ['ligneDepense.nomenclature', 'nouvelleLigneDepense.nomenclature'])
            ->orderBy('id')
            ->get();
    }

    public function libelle(MouvementCollectif $m): string
    {
        $f = number_format((float) $m->montant_modification, 0, ',', ' ');

        if ($m->type === 'recette') {
            $l = $m->ligneRecette ?? $m->nouvelleLigneRecette;
            return "{$l?->code_nomenclature} — {$l?->libelle_nomenclature} ({$f})" . ($m->nouvelle_ligne_recette_id ? ' — MN' : '');
        }

        $l = $m->ligneDepense ?? $m->nouvelleLigneDepense;
        return "{$l?->nomenclature?->code} — {$l?->nomenclature?->libelle} ({$f})" . ($m->nouvelle_ligne_depense_id ? ' — MN' : '');
    }

    /**
     * Enregistre les associations : [recette_id => [depense_id, …]]. Une dépense ne peut être cochée
     * que pour une recette. Les dépenses non cochées sont détachées.
     */
    public function associer(CollectifBudgetaire $collectif, array $associations): void
    {
        $recettes = $this->mouvements($collectif, 'recette')->pluck('id')->all();
        $depenses = $this->mouvements($collectif, 'depense')->pluck('id')->all();

        $vues = [];
        foreach ($associations as $recetteId => $ids) {
            foreach ((array) $ids as $id) {
                if (isset($vues[$id])) {
                    throw new DomainException('Une même dépense est rattachée à deux recettes : une dépense ne peut être financée que par une seule recette.');
                }
                $vues[$id] = (int) $recetteId;
            }
        }

        DB::transaction(function () use ($recettes, $depenses, $vues, $collectif) {
            DB::table('mouvements_collectifs')->whereIn('id', $depenses)->update(['mouvement_recette_id' => null]);

            foreach ($vues as $depenseId => $recetteId) {
                if (in_array((int) $depenseId, $depenses) && in_array($recetteId, $recettes)) {
                    DB::table('mouvements_collectifs')->where('id', $depenseId)->update(['mouvement_recette_id' => $recetteId]);
                }
            }

            ActivityLog::logAction($collectif, 'association_recettes_depenses', ['associations' => count($vues)]);
        });
    }

    /**
     * Équilibre par financement : chaque recette face au total de ses dépenses associées,
     * puis les recettes et dépenses non associées.
     */
    public function synthese(CollectifBudgetaire $collectif): array
    {
        $recettes = $this->mouvements($collectif, 'recette');
        $depenses = $this->mouvements($collectif, 'depense');

        $financements = $recettes->map(function ($r) use ($depenses) {
            $liees = $depenses->where('mouvement_recette_id', $r->id);
            return [
                'recette'  => $this->libelle($r),
                'montant'  => (float) $r->montant_modification,
                'depenses' => $liees->map(fn($d) => $this->libelle($d))->values()->all(),
                'total'    => (float) $liees->sum('montant_modification'),
                'ecart'    => (float) $r->montant_modification - (float) $liees->sum('montant_modification'),
                'associee' => $liees->isNotEmpty(),
            ];
        })->values();

        $nonAssociees = $depenses->whereNull('mouvement_recette_id');

        return [
            'financements'          => $financements->where('associee', true)->values(),
            'recettes_non_affectees' => $financements->where('associee', false)->values(),
            'depenses_non_associees' => $nonAssociees->map(fn($d) => ['libelle' => $this->libelle($d), 'montant' => (float) $d->montant_modification])->values(),
            'a_des_associations'    => $depenses->whereNotNull('mouvement_recette_id')->isNotEmpty(),
        ];
    }
}
