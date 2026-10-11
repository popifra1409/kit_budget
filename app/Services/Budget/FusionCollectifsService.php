<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\CollectifBudgetaire;
use App\Models\MouvementCollectif;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fusion de collectifs budgétaires DE MÊME STATUT — tous en projet, ou tous adoptés —
 * et du même exercice, en un nouveau collectif de ce statut :
 *  - collectifs ADOPTÉS : le nouveau collectif est adopté SANS réapplication (les mouvements ont
 *    déjà modifié les lignes) ; il reprend les budgets et prévisions rattachés ;
 *  - nouveau numéro, mouvements rattachés au nouveau collectif (origine mémorisée) ;
 *  - option : regroupement des mouvements portant sur la MÊME ligne existante (dépense ou recette) ;
 *    les virements et les créations de lignes ne sont jamais regroupés ;
 *  - collectifs d'origine conservés au statut « fusionne », liés au nouveau collectif ;
 *  - références des virements « en attente » mises à jour avec le nouveau numéro.
 * Défaire la fusion : possible tant que le collectif fusionné est en projet et sans regroupement.
 */
class FusionCollectifsService
{
    /** Mouvements actifs (non annulés) d'un ensemble de collectifs. */
    protected function mouvementsActifs(array $ids): Collection
    {
        return MouvementCollectif::whereIn('collectif_budgetaire_id', $ids)
            ->whereNull('date_annulation')
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->get();
    }

    /** Clé de regroupement : même type et même ligne EXISTANTE ; null = jamais regroupé. */
    protected function cleRegroupement(MouvementCollectif $m): ?string
    {
        return match (true) {
            $m->type === 'depense' && $m->ligne_depense_id && !$m->nouvelle_ligne_depense_id => "depense:{$m->ligne_depense_id}",
            $m->type === 'recette' && $m->ligne_recette_id && !$m->nouvelle_ligne_recette_id => "recette:{$m->ligne_recette_id}",
            default => null,
        };
    }

    /** Contrôles bloquants. */
    public function verifier(Collection $collectifs): void
    {
        if ($collectifs->count() < 2) {
            throw new DomainException('Sélectionnez au moins deux collectifs à fusionner.');
        }
        if ($nonEligibles = $collectifs->whereNotIn('statut', ['projet', 'adopte'])->pluck('numero')->implode(', ')) {
            throw new DomainException("Seuls des collectifs en projet ou adoptés peuvent être fusionnés. Non éligibles (annulés ou déjà fusionnés) : {$nonEligibles}.");
        }
        if ($collectifs->pluck('statut')->unique()->count() > 1) {
            throw new DomainException('Les collectifs doivent avoir le même statut : tous en projet, ou tous adoptés. '
                . 'Un collectif adopté a déjà modifié le budget, un projet non.');
        }
        if ($collectifs->pluck('exercice_id')->unique()->count() > 1) {
            throw new DomainException('Les collectifs à fusionner doivent appartenir au même exercice.');
        }
        if ($collectifs->first()->exercice?->estLectureSeule()) {
            throw new DomainException("L'exercice de ces collectifs est clôturé : ils ne peuvent plus être fusionnés.");
        }
    }

    /** Aperçu du collectif qui résulterait de la fusion. */
    public function apercu(Collection $collectifs, bool $regrouper): array
    {
        $mouvements = $this->mouvementsActifs($collectifs->pluck('id')->all());
        $groupes = $mouvements->groupBy(fn($m) => $this->cleRegroupement($m) ?? "seul:{$m->id}");
        $regroupables = $groupes->filter(fn($g, $cle) => !str_starts_with($cle, 'seul:') && $g->count() > 1);

        $recettes = (float) $mouvements->where('type', 'recette')->sum('montant_modification');
        $depenses = (float) $mouvements->where('type', 'depense')->sum('montant_modification');

        return [
            'statut'             => $collectifs->first()?->statut,
            'collectifs'         => $collectifs->pluck('numero')->implode(', '),
            'mouvements'         => $mouvements->count(),
            'mouvements_apres'   => $regrouper ? $mouvements->count() - $regroupables->sum(fn($g) => $g->count() - 1) : $mouvements->count(),
            'recettes'           => $recettes,
            'depenses'           => $depenses,
            'virements'          => (float) $mouvements->where('type', 'virement')->sum('montant_modification'),
            'equilibre'          => round($recettes - $depenses, 2),
            'lignes_regroupables' => $regroupables->count(),
        ];
    }

    public function fusionner(array $ids, string $libelle, string $dateCollectif, ?string $observations = null, bool $regrouper = false): CollectifBudgetaire
    {
        $collectifs = CollectifBudgetaire::whereIn('id', $ids)->orderBy('date_collectif')->orderBy('id')->get();
        $this->verifier($collectifs);

        return DB::transaction(function () use ($collectifs, $libelle, $dateCollectif, $observations, $regrouper) {
            $statut = $collectifs->first()->statut;   // tous identiques (contrôlé)
            $adopte = $statut === 'adopte';

            // Rattachements (budgets, prévisions) de chaque collectif d'origine : repris par la fusion,
            // mémorisés pour pouvoir défaire la fusion
            $origines = $collectifs->map(fn($c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'libelle' => $c->libelle,
                'budgets'    => $c->budgets()->get()->modelKeys(),
                'previsions' => $c->previsionRecettes()->get()->modelKeys(),
            ])->values()->all();

            $fusion = CollectifBudgetaire::create([
                'exercice_id'    => $collectifs->first()->exercice_id,
                'libelle'        => $libelle,
                'date_collectif' => $dateCollectif,
                'statut'         => $statut,   // ⚠️ adopté SANS appliquer() : les mouvements sont déjà appliqués
                'date_adoption'  => $adopte ? $collectifs->max('date_adoption') : null,
                'reference'      => $adopte ? mb_substr($collectifs->pluck('reference')->filter()->implode(' ; '), 0, 100) ?: null : null,
                'observations'   => trim('Fusion des collectifs ' . $collectifs->pluck('numero')->implode(', ') . ".\n" . ($observations ?? '')),
                'created_by'     => auth()->id(),
            ]);

            // 1. Rattacher les mouvements au nouveau collectif, en mémorisant leur origine
            foreach ($collectifs as $c) {
                DB::table('mouvements_collectifs')->where('collectif_budgetaire_id', $c->id)->update([
                    'collectif_origine_id'    => DB::raw('COALESCE(collectif_origine_id, collectif_budgetaire_id)'),
                    'collectif_budgetaire_id' => $fusion->id,
                    'updated_at'              => now(),
                ]);
            }

            // 2. Regroupement optionnel des mouvements sur une même ligne existante
            $regroupements = [];
            if ($regrouper) {
                $this->mouvementsActifs([$fusion->id])
                    ->groupBy(fn($m) => $this->cleRegroupement($m) ?? "seul:{$m->id}")
                    ->filter(fn($g, $cle) => !str_starts_with($cle, 'seul:') && $g->count() > 1)
                    ->each(function ($groupe, $cle) use (&$regroupements) {
                        $garde = $groupe->first();
                        $total = (float) $groupe->sum('montant_modification');
                        $motifs = $groupe->pluck('motif')->filter()->unique()->implode(' ; ');

                        DB::table('mouvements_collectifs')->where('id', $garde->id)->update([
                            'montant_modification' => $total,
                            'motif'                => mb_substr("[Regroupement] {$motifs}", 0, 1000),
                            'updated_at'           => now(),
                        ]);
                        $groupe->slice(1)->each->delete();

                        $regroupements[] = ['ligne' => $cle, 'mouvements' => $groupe->count(), 'montant' => $total];
                    });
            }

            // 3. Virements en attente : référence au nouveau numéro
            $virementIds = DB::table('mouvements_collectifs')->where('collectif_budgetaire_id', $fusion->id)
                ->whereNotNull('virement_budgetaire_id')->pluck('virement_budgetaire_id');
            if ($virementIds->isNotEmpty()) {
                DB::table('virements_budgetaires')->whereIn('id', $virementIds)
                    ->update(['reference_decision' => $fusion->numero, 'updated_at' => now()]);
            }

            // 4. Budgets et prévisions rattachés : repris par le collectif fusionné
            $fusion->budgets()->syncWithoutDetaching(collect($origines)->pluck('budgets')->flatten()->unique()->all());
            $fusion->previsionRecettes()->syncWithoutDetaching(collect($origines)->pluck('previsions')->flatten()->unique()->all());
            foreach ($collectifs as $c) {
                $c->budgets()->detach();
                $c->previsionRecettes()->detach();
            }

            // 5. Collectifs d'origine : conservés, statut « fusionne »
            foreach ($collectifs as $c) {
                $c->update(['statut' => 'fusionne', 'fusionne_dans_id' => $fusion->id, 'date_fusion' => now()]);
                ActivityLog::logAction($c, 'fusionne', ['dans' => $fusion->numero]);
            }

            $fusion->update(['fusion_meta' => [
                'origines' => $origines,
                'statut_origine' => $statut,
                'regroupements' => $regroupements,
                'regroupe' => !empty($regroupements),
            ]]);

            ActivityLog::logAction($fusion, 'fusion_collectifs', [
                'origines' => $collectifs->pluck('numero')->implode(', '),
                'statut'   => $statut,
                'regroupements' => count($regroupements),
            ]);

            return $fusion;
        });
    }

    /** Défaire : le collectif fusionné a toujours le statut de ses collectifs d'origine, sans regroupement. */
    public function peutDefaire(CollectifBudgetaire $fusion): bool
    {
        $statutOrigine = $fusion->fusion_meta['statut_origine'] ?? 'projet';

        return $fusion->estIssuDeFusion()
            && $fusion->statut === $statutOrigine
            && empty($fusion->fusion_meta['regroupe'])
            && !$fusion->exercice?->estLectureSeule();
    }

    /** Défaire la fusion : chaque mouvement retourne à son collectif d'origine ; le collectif fusionné est supprimé. */
    public function defaire(CollectifBudgetaire $fusion): void
    {
        if (!$this->peutDefaire($fusion)) {
            throw new DomainException(!empty($fusion->fusion_meta['regroupe'])
                ? 'Des mouvements ont été regroupés lors de la fusion : elle ne peut pas être défaite automatiquement.'
                : 'Le statut du collectif fusionné a changé depuis la fusion (adoption ou annulation), ou l\'exercice est clôturé : la fusion ne peut plus être défaite.');
        }

        DB::transaction(function () use ($fusion) {
            $origines = collect($fusion->fusion_meta['origines'] ?? []);

            // Mouvements rendus à leur collectif d'origine
            DB::table('mouvements_collectifs')->where('collectif_budgetaire_id', $fusion->id)->whereNotNull('collectif_origine_id')
                ->update(['collectif_budgetaire_id' => DB::raw('collectif_origine_id'), 'collectif_origine_id' => null, 'updated_at' => now()]);

            $statutOrigine = $fusion->fusion_meta['statut_origine'] ?? 'projet';

            foreach (CollectifBudgetaire::whereIn('id', $origines->pluck('id'))->get() as $c) {
                $c->update(['statut' => $statutOrigine, 'fusionne_dans_id' => null, 'date_fusion' => null]);

                // Rattachements d'origine rétablis
                $o = $origines->firstWhere('id', $c->id);
                $c->budgets()->syncWithoutDetaching($o['budgets'] ?? []);
                $c->previsionRecettes()->syncWithoutDetaching($o['previsions'] ?? []);

                $virementIds = DB::table('mouvements_collectifs')->where('collectif_budgetaire_id', $c->id)
                    ->whereNotNull('virement_budgetaire_id')->pluck('virement_budgetaire_id');
                DB::table('virements_budgetaires')->whereIn('id', $virementIds)->update(['reference_decision' => $c->numero, 'updated_at' => now()]);

                ActivityLog::logAction($c, 'fusion_defaite', ['collectif_fusionne' => $fusion->numero]);
            }

            ActivityLog::logAction($fusion, 'fusion_defaite', ['origines' => $origines->pluck('numero')->implode(', ')]);
            $fusion->budgets()->detach();
            $fusion->previsionRecettes()->detach();
            $fusion->delete();
        });
    }
}
