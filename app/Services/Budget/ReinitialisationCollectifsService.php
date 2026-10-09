<?php

namespace App\Services\Budget;

use App\Models\CollectifBudgetaire;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\LignePrevisionRecette;
use App\Models\MouvementCollectif;
use App\Models\User;
use App\Models\VirementBudgetaire;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Revient sur les effets de lignes des collectifs budgétaires, puis les supprime.
 *
 * analyser() est strictement en lecture seule : il doit être présenté avant
 * reinitialiser(), qui refuse tout le lot si un seul blocage subsiste.
 */
class ReinitialisationCollectifsService
{
    /** Tolérance d'arrondi pour les comparaisons de soldes. */
    private const SEUIL = 0.01;

    /**
     * @param  bool $tolererSurengagement Autorise la réinitialisation même quand le
     *                                    retrait ramène une ligne sous ce qui est
     *                                    déjà engagé. La ligne se retrouve
     *                                    surengagée : à n'utiliser que pour
     *                                    redresser un exercice, les engagements
     *                                    restent en place.
     * @return array{collectifs: array, nb_collectifs: int, nb_blocages: int, nb_prets: int, nb_ecartes: int, nb_avertissements: int, peut_reinitialiser: bool}
     */
    public function analyser(array $collectifIds, bool $tolererSurengagement = false): array
    {
        $collectifs = CollectifBudgetaire::whereIn('id', $collectifIds)
            ->with('mouvements')
            // Ordre d'annulation : du plus récent au plus ancien
            ->orderByDesc('date_collectif')
            ->orderByDesc('id')
            ->get();

        $collectifs->each(fn(CollectifBudgetaire $c) => $c->mouvements->loadMissing('virementBudgetaire'));

        // Simulation portée par la sélection entière, et non collectif par
        // collectif : deux collectifs qui touchent la même ligne pourraient
        // chacun passer le test et, supprimés ensemble, rendre le disponible
        // négatif.
        $depenses = $this->evaluerDepenses($this->simulerDepenses($collectifs), $collectifs, $tolererSurengagement);
        $recettes = $this->evaluerRecettes($this->simulerRecettes($collectifs), $collectifs);

        $rapports = $collectifs
            ->map(fn(CollectifBudgetaire $c) => $this->analyserCollectif($c, $depenses, $recettes))
            ->all();

        $nbBlocages = array_sum(array_map(fn($r) => count($r['blocages']), $rapports));

        return [
            'collectifs'         => $rapports,
            'nb_collectifs'      => count($rapports),
            'nb_blocages'        => $nbBlocages,
            'nb_prets'           => count(array_filter($rapports, fn($r) => $r['blocages'] === [])),
            'nb_ecartes'         => count(array_filter($rapports, fn($r) => $r['blocages'] !== [])),
            'nb_avertissements'  => array_sum(array_map(fn($r) => count($r['avertissements']), $rapports)),
            // Une ligne surengagée dans un collectif qui de toute façon ne passera pas
            // n'a rien à faire dans le résumé : elle ne bougera pas.
            'nb_surengagees'     => array_sum(array_map(
                fn($r) => $r['blocages'] === [] ? $r['nb_surengagees'] : 0, $rapports
            )),
            'peut_reinitialiser' => $rapports !== [] && $nbBlocages === 0,
        ];
    }

    /**
     * Réinitialise les collectifs sans blocage et laisse les autres inchangés :
     * un collectif bloqué ne doit pas empêcher de repartir de zéro.
     *
     * @return array{collectifs: array, nb_collectifs: int, nb_blocages: int, nb_avertissements: int, ecartes: array, nb_ecartes: int, peut_reinitialiser: bool}
     *
     * @throws RuntimeException si rien n'est réinitialisable
     */
    public function reinitialiser(array $collectifIds, ?User $user = null, bool $tolererSurengagement = false): array
    {
        return DB::transaction(function () use ($collectifIds, $user, $tolererSurengagement) {
            $demande = $this->analyser($collectifIds, $tolererSurengagement);

            $prets = array_values(array_filter($demande['collectifs'], fn($r) => $r['blocages'] === []));

            if ($prets === []) {
                throw new RuntimeException($this->messageRefus($demande, $tolererSurengagement));
            }

            // Écarter des collectifs ne fait que réduire les retraits simulés : un
            // collectif prêt le reste. L'analyse est relue sur le périmètre exécuté
            // pour que le rapport rendu ne parle que de lui.
            $rapport = $this->analyser(array_column($prets, 'id'), $tolererSurengagement);

            if (!$rapport['peut_reinitialiser']) {
                throw new RuntimeException($this->messageRefus($rapport, $tolererSurengagement));
            }

            $exercices = [];

            foreach ($rapport['collectifs'] as $resume) {
                $collectif = CollectifBudgetaire::with('mouvements')->find($resume['id']);

                \App\Models\ActivityLog::logAction($collectif, 'reinitialiser', [
                    'ancien_statut' => $collectif->statut,
                    'nouveau_statut' => 'supprime',
                    'par'           => $user?->name ?? 'Système',
                    'motif'         => 'Réinitialisation : annulation des effets puis suppression du collectif',
                    'mouvements'    => $resume['nb_mouvements'],
                    'lignes_crees'  => $resume['nb_lignes_crees'],
                    'lignes_surengagees' => $resume['nb_surengagees'],
                ]);

                $exercices[] = $collectif->exercice_id;
                $this->annulerPuisSupprimer($collectif, $user);
            }

            // Le tableau de bord lit aussi un instantané persisté : sans ce
            // recalcul, les widgets gardent les chiffres d'avant la réinitialisation.
            foreach (array_filter(array_unique($exercices)) as $exerciceId) {
                Exercice::find($exerciceId)?->mettreAJourStatistiques();
            }

            $rapport['ecartes'] = array_values(array_filter(
                $demande['collectifs'], fn($r) => $r['blocages'] !== []
            ));
            $rapport['nb_ecartes'] = count($rapport['ecartes']);

            return $rapport;
        });
    }

    private function messageRefus(array $rapport, bool $tolererSurengagement = false): string
    {
        $blocages = [];

        foreach ($rapport['collectifs'] as $collectif) {
            foreach ($collectif['blocages'] as $blocage) {
                $blocages[] = $blocage['message'];
            }
        }

        return 'Réinitialisation impossible : aucun collectif sélectionné n\'est prêt. '
            . implode(' ', array_slice($blocages, 0, 3))
            . ($tolererSurengagement ? '' : ' Vous pouvez autoriser le surengagement pour passer les blocages de disponible.');
    }

    private function annulerPuisSupprimer(CollectifBudgetaire $collectif, ?User $user): void
    {
        $mouvements = $collectif->mouvements
            ->sortByDesc('id')
            ->filter(fn(MouvementCollectif $m) => $m->statut !== 'annule');

        // Capturé AVANT l'annulation : annuler() remet collectif_creation_id à null,
        // les lignes créées ne seraient plus rattachables à ce collectif.
        $lignesCreesDepense = $collectif->mouvements->pluck('nouvelle_ligne_depense_id')->filter()->unique()->all();
        $lignesCreesRecette = $collectif->mouvements->pluck('nouvelle_ligne_recette_id')->filter()->unique()->all();

        // Virements mis en mouvement par ce collectif : sans eux il n'existerait
        // pas. S'ils restaient seulement « rejetés », ils continueraient d'apparaître
        // dans les listes de mouvements de crédits, accrochés à des lignes supprimées.
        $virementsCrees = $collectif->mouvements
            ->filter(fn(MouvementCollectif $m) => $m->virementBudgetaire?->origine === 'collectif')
            ->pluck('virement_budgetaire_id')->filter()->unique()->all();

        $lignesToucheesDepense = $this->lignesDepenseConcernees($collectif);
        $lignesToucheesRecette = $this->lignesRecetteConcernees($collectif);

        foreach ($mouvements as $mouvement) {
            $mouvement->annuler($user);
        }

        // Remises à zéro par annuler(), ces lignes n'existeraient pas sans ce collectif.
        LigneBudgetaire::withTrashed()->whereIn('id', $lignesCreesDepense)->get()
            ->each(fn(LigneBudgetaire $l) => $l->delete());
        LignePrevisionRecette::withTrashed()->whereIn('id', $lignesCreesRecette)->get()
            ->each(fn(LignePrevisionRecette $l) => $l->delete());

        VirementBudgetaire::withTrashed()->whereIn('id', $virementsCrees)->get()
            ->each(fn(VirementBudgetaire $v) => $v->delete());

        // Pas de SoftDeletes ici : la suppression est réelle et entraîne en cascade
        // les mouvements et les lignes de pivot budget_collectif / prevision_recette_collectif.
        $collectif->delete();

        $this->recalculer(
            $lignesToucheesDepense->pluck('id')->all(),
            $lignesToucheesRecette->pluck('id')->all()
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, LigneBudgetaire>
     */
    private function lignesDepenseConcernees(CollectifBudgetaire $collectif): \Illuminate\Support\Collection
    {
        $ids = $collectif->mouvements
            ->flatMap(function (MouvementCollectif $m) {
                $ids = [
                    $m->ligne_depense_id,
                    $m->nouvelle_ligne_depense_id,
                    $m->ligne_source_id,
                    $m->ligne_destination_id,
                ];

                // Un mouvement 'virement' ne porte pas les lignes : elles sont sur le
                // VirementBudgetaire lié. Sans elles, ni l'analyse ni le recalcul final
                // ne verraient les lignes alimentées ou amputées par le virement.
                if ($m->virementBudgetaire) {
                    $ids[] = $m->virementBudgetaire->ligne_source_id;
                    $ids[] = $m->virementBudgetaire->ligne_destination_id;
                }

                return $ids;
            })
            ->filter()->unique();

        return LigneBudgetaire::withTrashed()->whereIn('id', $ids)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, LignePrevisionRecette>
     */
    private function lignesRecetteConcernees(CollectifBudgetaire $collectif): \Illuminate\Support\Collection
    {
        $ids = $collectif->mouvements
            ->flatMap(fn(MouvementCollectif $m) => [$m->ligne_recette_id, $m->nouvelle_ligne_recette_id])
            ->filter()->unique();

        return LignePrevisionRecette::withTrashed()->whereIn('id', $ids)->get();
    }

    /**
     * Recalcul des lignes encore vivantes, puis des totaux de leur budget / prévision.
     * Les identifiants sont relus en base : après suppression, seules les lignes
     * réellement vivantes doivent être recalculées.
     *
     * @param list<int> $idsDepense
     * @param list<int> $idsRecette
     */
    private function recalculer(array $idsDepense, array $idsRecette): void
    {
        $budgetIds = [];

        foreach (LigneBudgetaire::withTrashed()->whereIn('id', $idsDepense)->get() as $ligne) {
            if ($ligne->trashed()) {
                continue;
            }
            $ligne->recalculerDepuisEngagements();
            $budgetIds[] = $ligne->budget_id;
        }

        foreach (LigneBudgetaire::whereIn('budget_id', $budgetIds)->get() as $ligne) {
            $ligne->recalculerDepuisEngagements();
        }

        foreach (array_unique($budgetIds) as $budgetId) {
            \App\Models\Budget::find($budgetId)?->recalculerTotaux();
        }

        $previsionIds = [];

        foreach (LignePrevisionRecette::withTrashed()->whereIn('id', $idsRecette)->get() as $ligne) {
            if ($ligne->trashed()) {
                continue;
            }
            $ligne->recalculerRectifie();
            $ligne->recalculer();
            $previsionIds[] = $ligne->prevision_recette_id;
        }

        foreach (array_unique($previsionIds) as $previsionId) {
            $prevision = \App\Models\PrevisionRecette::find($previsionId);
            $prevision?->lignesPrevisions()->get()->each(
                fn(LignePrevisionRecette $l) => [$l->recalculerRectifie(), $l->recalculer()]
            );
        }
    }

    // ════════════════════════════════════════════════════════════
    // ANALYSE
    // ════════════════════════════════════════════════════════════

    /**
     * Un engagement ne bloque pas la réinitialisation : ce qui la bloque, c'est un
     * retrait qui ferait passer le disponible sous zéro. On simule donc le montant
     * de chaque ligne une fois les collectifs retirés, et on le compare à ce qui a
     * déjà été consommé (engagements côté dépense, recouvrements côté recette).
     *
     * @param  \Illuminate\Support\Collection<int, CollectifBudgetaire> $collectifs
     * @return array<int, array> lignes simulées, indexées par id
     */
    private function simulerDepenses(\Illuminate\Support\Collection $collectifs): array
    {
        $lignes = [];

        foreach ($collectifs as $collectif) {
            foreach ($this->lignesDepenseConcernees($collectif) as $ligne) {
                if (!isset($lignes[$ligne->id])) {
                    $engagements = $ligne->requeteEngagementsActifs()
                        ->selectRaw('count(*) as nb, coalesce(sum(le.montant), 0) as montant')
                        ->first();

                    $lignes[$ligne->id] = [
                        'code'           => $ligne->nomenclature?->code ?? "ligne #{$ligne->id}",
                        'libelle'        => $ligne->nomenclature?->libelle ?? '',
                        'creee'          => false,
                        'avant'          => round($ligne->getBudgetRectifieReel(), 2),
                        'engage'         => round((float) $engagements->montant, 2),
                        'nb_engagements' => (int) $engagements->nb,
                        'retire'         => 0.0,
                    ];
                }

                if ($collectif->mouvements->contains('nouvelle_ligne_depense_id', $ligne->id)) {
                    $lignes[$ligne->id]['creee'] = true;
                }

                $lignes[$ligne->id]['retire'] += $this->contributionDepense($collectif, $ligne);
            }
        }

        foreach ($lignes as $id => $ligne) {
            $retire = round($ligne['retire'], 2);

            // Une ligne créée par un collectif de la sélection disparaît : elle ne
            // laisse aucun crédit derrière elle.
            $apres = $ligne['creee'] ? 0.0 : round($ligne['avant'] - $retire, 2);

            $lignes[$id]['retire']      = $retire;
            $lignes[$id]['apres']       = $apres;
            $lignes[$id]['dispo_apres'] = round($apres - $ligne['engage'], 2);
        }

        return $lignes;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CollectifBudgetaire> $collectifs
     * @return array<int, array>
     */
    private function simulerRecettes(\Illuminate\Support\Collection $collectifs): array
    {
        $lignes = [];

        foreach ($collectifs as $collectif) {
            foreach ($this->lignesRecetteConcernees($collectif) as $ligne) {
                if (!isset($lignes[$ligne->id])) {
                    // La colonne stockée peut être obsolète ; le cumul mensuel est
                    // la source.
                    $recouvre = max(
                        (float) $ligne->montant_recouvre,
                        (float) $ligne->previsionsMensuelles()->sum('montant_recouvre')
                    );

                    $lignes[$ligne->id] = [
                        'code'           => $ligne->nomenclature?->code ?? "ligne #{$ligne->id}",
                        'libelle'        => $ligne->nomenclature?->libelle ?? '',
                        'creee'          => false,
                        'avant'          => round($ligne->getMontantRectifieReel(), 2),
                        'recouvre'       => round($recouvre, 2),
                        'nb_recouvrements' => (int) $ligne->recettesReelles()->count(),
                        'retire'         => 0.0,
                    ];
                }

                if ($collectif->mouvements->contains('nouvelle_ligne_recette_id', $ligne->id)) {
                    $lignes[$ligne->id]['creee'] = true;
                }

                $lignes[$ligne->id]['retire'] += $this->contributionRecette($collectif, $ligne);
            }
        }

        foreach ($lignes as $id => $ligne) {
            $retire = round($ligne['retire'], 2);
            $apres  = $ligne['creee'] ? 0.0 : round($ligne['avant'] - $retire, 2);

            $lignes[$id]['retire']        = $retire;
            $lignes[$id]['apres']         = $apres;
            $lignes[$id]['restant_apres'] = round($apres - $ligne['recouvre'], 2);
        }

        return $lignes;
    }

    /**
     * Effet en solde d'un collectif sur une ligne de dépense : mouvements de
     * crédits non annulés + virements exécutés qu'il pilote. Un collectif non
     * adopté n'a encore aucun effet à retirer.
     */
    private function contributionDepense(CollectifBudgetaire $collectif, LigneBudgetaire $ligne): float
    {
        if ($collectif->statut !== 'adopte') {
            return 0.0;
        }

        $contribution = 0.0;

        foreach ($collectif->mouvements as $mouvement) {
            if ($this->estAnnule($mouvement)) {
                continue;
            }

            if ($mouvement->type === 'depense' && (int) $mouvement->ligne_depense_id === $ligne->id) {
                $contribution += (float) $mouvement->montant_modification;
            }

            if ($mouvement->type === 'virement') {
                $contribution += $this->effetVirement($mouvement, $ligne->id);
            }
        }

        return $contribution;
    }

    private function contributionRecette(CollectifBudgetaire $collectif, LignePrevisionRecette $ligne): float
    {
        if ($collectif->statut !== 'adopte') {
            return 0.0;
        }

        $contribution = 0.0;

        foreach ($collectif->mouvements as $mouvement) {
            if ($this->estAnnule($mouvement)) {
                continue;
            }

            if ($mouvement->type === 'recette' && (int) $mouvement->ligne_recette_id === $ligne->id) {
                $contribution += (float) $mouvement->montant_modification;
            }
        }

        return $contribution;
    }

    /**
     * Contribution d'un virement exécuté au budget rectifié d'une ligne :
     * + montant en destination, − montant en source (getBudgetRectifieReel ne
     * compte que les virements 'execute', qui seront rejetés par l'annulation).
     */
    private function effetVirement(MouvementCollectif $mouvement, int $ligneId): float
    {
        $virement = $mouvement->virementBudgetaire;

        if (!$virement || $virement->statut !== 'execute') {
            return 0.0;
        }

        return (float) $virement->montant * (int) (
            ((int) $virement->ligne_destination_id === $ligneId)
            - ((int) $virement->ligne_source_id === $ligneId)
        );
    }

    private function estAnnule(MouvementCollectif $mouvement): bool
    {
        return $mouvement->statut === 'annule' || $mouvement->date_annulation !== null;
    }

    /**
     * @param  array<int, array>                                        $lignes
     * @param  \Illuminate\Support\Collection<int, CollectifBudgetaire> $collectifs
     * @return array<int, array> chaque ligne enrichie d'un 'blocage' (ou null) et d'un 'avertissement'
     */
    private function evaluerDepenses(array $lignes, \Illuminate\Support\Collection $collectifs, bool $tolererSurengagement): array
    {
        $idsSelection = $collectifs->pluck('id')->all();
        $virementsPropres = $collectifs->flatMap(fn(CollectifBudgetaire $c) => $c->mouvements->pluck('virement_budgetaire_id'))
            ->filter()->unique()->all();

        foreach ($lignes as $id => $ligne) {
            $blocage = null;
            $avertissement = null;

            if ($ligne['creee']) {
                if ($ligne['nb_engagements'] > 0) {
                    $blocage = [
                        'type'    => 'engagement',
                        'message' => "La ligne {$ligne['code']} — {$ligne['libelle']}, créée par ce collectif, supporte "
                            . "{$ligne['nb_engagements']} engagement(s) pour " . $this->fcfa($ligne['engage'])
                            . ' : elle ne peut pas être supprimée.',
                    ];
                } else {
                    $blocage = $this->dependancesExternes($id, $idsSelection, $virementsPropres, $ligne);
                }
            }

            if (!$blocage && $ligne['dispo_apres'] < -self::SEUIL) {
                $detail = "Réinitialiser ramènerait la ligne {$ligne['code']} — {$ligne['libelle']} à "
                    . $this->fcfa($ligne['apres']) . ' alors que ' . $this->fcfa($ligne['engage'])
                    . " est déjà engagé ({$ligne['nb_engagements']} engagement(s)) : "
                    . 'le disponible deviendrait négatif (' . $this->fcfa($ligne['dispo_apres']) . ').';

                // Le surengagement est un déséquilibre de trésorerie, pas une écriture
                // orpheline : les engagements restent valables, la ligne devra être
                // abondée. C'est le seul blocage qu'on accepte de convertir en avis.
                if ($tolererSurengagement) {
                    $avertissement = ['type' => 'disponible', 'message' => $detail];
                } else {
                    $blocage = ['type' => 'disponible', 'message' => $detail];
                }
            }

            $lignes[$id]['blocage']       = $blocage;
            $lignes[$id]['avertissement'] = $avertissement;
        }

        return $lignes;
    }

    /**
     * @param  array<int, array>                                     $lignes
     * @param  \Illuminate\Support\Collection<int, CollectifBudgetaire> $collectifs
     * @return array<int, array>
     */
    private function evaluerRecettes(array $lignes, \Illuminate\Support\Collection $collectifs): array
    {
        $idsSelection = $collectifs->pluck('id')->all();

        foreach ($lignes as $id => $ligne) {
            $blocage = null;

            if ($ligne['creee'] && $ligne['nb_recouvrements'] > 0) {
                $blocage = [
                    'type'    => 'recouvrement',
                    'message' => "La ligne de recette {$ligne['code']} — {$ligne['libelle']}, créée par ce collectif, a "
                        . 'déjà donné lieu à ' . $this->fcfa($ligne['recouvre']) . " de recouvrements ({$ligne['nb_recouvrements']} "
                        . 'écriture(s)) : elle ne peut pas être supprimée.',
                ];
            }

            if (!$blocage && $ligne['restant_apres'] < -self::SEUIL) {
                $blocage = [
                    'type'    => 'recouvrement',
                    'message' => "Réinitialiser ramènerait la prévision {$ligne['code']} — {$ligne['libelle']} à "
                        . $this->fcfa($ligne['apres']) . ' alors que ' . $this->fcfa($ligne['recouvre'])
                        . ' a déjà été recouvré : le reste à recouvrer deviendrait négatif ('
                        . $this->fcfa($ligne['restant_apres']) . ').',
                ];
            }

            // Une ligne de recette créée n'a pas d'autre dépendance budgétaire :
            // les mouvements de crédits ne circulent que sur les dépenses.
            if (!$blocage && $ligne['creee']) {
                $mouvementsExternes = MouvementCollectif::where(function ($q) use ($id) {
                        $q->where('nouvelle_ligne_recette_id', $id)->orWhere('ligne_recette_id', $id);
                    })
                    ->whereNotIn('collectif_budgetaire_id', $idsSelection)
                    ->where(fn($q) => $q->whereNull('statut')->orWhere('statut', '!=', 'annule'))
                    ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
                    ->count();

                if ($mouvementsExternes > 0) {
                    $blocage = [
                        'type'    => 'collectif_exterieur',
                        'message' => "La ligne de recette {$ligne['code']} a été créée ou modifiée par {$mouvementsExternes} "
                            . "mouvement(s) d'un collectif hors sélection : réinitialisez-les ensemble.",
                    ];
                }
            }

            $lignes[$id]['blocage'] = $blocage;
            // Recettes : le reste à recouvrer négatif reste bloquant, aucun avis.
            $lignes[$id]['avertissement'] = null;
        }

        return $lignes;
    }

    /**
     * Une ligne créée ne peut disparaître que si rien, hors de la sélection, n'en
     * dépend : la suppression d'une ligne fait cascade sur les virements qui la
     * référencent et efface les mouvements des autres collectifs.
     *
     * @param  array<string, mixed> $ligne
     */
    private function dependancesExternes(int $ligneId, array $idsSelection, array $virementsPropres, array $ligne): ?array
    {
        $virementsExternes = VirementBudgetaire::where(function ($q) use ($ligneId) {
                $q->where('ligne_source_id', $ligneId)->orWhere('ligne_destination_id', $ligneId);
            })
            ->whereNotIn('statut', ['rejete', 'annule'])
            ->when($virementsPropres !== [], fn($q) => $q->whereNotIn('id', $virementsPropres))
            ->count();

        if ($virementsExternes > 0) {
            return [
                'type'    => 'virement',
                'message' => "La ligne {$ligne['code']}, créée par ce collectif, est utilisée par {$virementsExternes} "
                    . "mouvement(s) de crédits extérieurs non rejeté(s).",
            ];
        }

        $collectifsExternes = MouvementCollectif::where('ligne_depense_id', $ligneId)
            ->whereNotIn('collectif_budgetaire_id', $idsSelection)
            ->where(fn($q) => $q->whereNull('statut')->orWhere('statut', '!=', 'annule'))
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->count();

        if ($collectifsExternes > 0) {
            return [
                'type'    => 'collectif_exterieur',
                'message' => "La ligne {$ligne['code']}, créée par ce collectif, est modifiée par {$collectifsExternes} "
                    . "mouvement(s) d'un collectif hors sélection : réinitialisez-les ensemble.",
            ];
        }

        return null;
    }

    private function fcfa(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' FCFA';
    }

    /**
     * @param  array<int, array> $depenses
     * @param  array<int, array> $recettes
     */
    private function analyserCollectif(CollectifBudgetaire $collectif, array $depenses, array $recettes): array
    {
        $blocages = [];
        $avertissements = [];

        if ($collectif->exercice?->estCloture()) {
            $blocages[] = [
                'type'    => 'exercice_clos',
                'message' => "L'exercice {$collectif->exercice->annee} est clôturé : ses écritures ne peuvent plus être modifiées.",
            ];
        }

        $impacts = [];

        foreach ($this->lignesDepenseConcernees($collectif) as $ligne) {
            $s = $depenses[$ligne->id];

            $impacts[] = [
                'type'     => 'Dépense',
                'code'     => $s['code'],
                'libelle'  => $s['libelle'],
                'creee'    => $s['creee'],
                'avant'    => $s['avant'],
                'retire'   => round($this->contributionDepense($collectif, $ligne), 2),
                'apres'    => $s['apres'],
                'consomme' => $s['engage'],
                'solde'    => $s['dispo_apres'],
                'solde_label' => 'Disponible après',
                'surengage' => $s['dispo_apres'] < -self::SEUIL,
            ];

            if ($s['blocage']) {
                $blocages[] = $s['blocage'];
            }

            if ($s['avertissement']) {
                $avertissements[] = $s['avertissement'];
            }
        }

        foreach ($this->lignesRecetteConcernees($collectif) as $ligne) {
            $s = $recettes[$ligne->id];

            $impacts[] = [
                'type'     => 'Recette',
                'code'     => $s['code'],
                'libelle'  => $s['libelle'],
                'creee'    => $s['creee'],
                'avant'    => $s['avant'],
                'retire'   => round($this->contributionRecette($collectif, $ligne), 2),
                'apres'    => $s['apres'],
                'consomme' => $s['recouvre'],
                'solde'    => $s['restant_apres'],
                'solde_label' => 'Reste après',
                'surengage' => $s['restant_apres'] < -self::SEUIL,
            ];

            if ($s['blocage']) {
                $blocages[] = $s['blocage'];
            }
        }

        $virements = VirementBudgetaire::whereIn('id', $collectif->mouvements->pluck('virement_budgetaire_id')->filter())->get();

        return [
            'id'             => $collectif->id,
            'numero'         => $collectif->numero,
            'libelle'        => $collectif->libelle,
            'statut'         => $collectif->statut,
            'date_collectif' => $collectif->date_collectif?->format('d/m/Y'),
            'nb_mouvements'  => $collectif->mouvements->count(),
            'nb_lignes_crees' => $collectif->mouvements
                ->flatMap(fn(MouvementCollectif $m) => [$m->nouvelle_ligne_depense_id, $m->nouvelle_ligne_recette_id])
                ->filter()->unique()->count(),
            'nb_virements'   => $virements->count(),
            'effet_total'    => (float) $collectif->mouvements
                ->where('type', '!=', 'virement')->sum('montant_modification'),
            'impacts'        => $impacts,
            'nb_surengagees' => count(array_filter($impacts, fn($i) => $i['surengage'])),
            'blocages'       => array_values($blocages),
            'avertissements' => array_values($avertissements),
        ];
    }
}
