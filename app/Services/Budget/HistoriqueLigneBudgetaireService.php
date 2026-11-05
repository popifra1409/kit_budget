<?php
// app/Services/Budget/HistoriqueLigneBudgetaireService.php

namespace App\Services\Budget;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Models\MouvementCollectif;
use App\Models\VirementBudgetaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Historique des modifications d'une ligne budgétaire depuis sa dotation initiale.
 *
 * SOURCE UNIQUE pour le certificat d'engagement et la fiche de contrôle des engagements.
 * Mêmes règles que LigneBudgetaire::getBudgetRectifieReel() :
 *   dotation finale = dotation initiale + Σ modifications = budget rectifié.
 *
 * Natures :
 *  - augmentation      : mouvement de dépense positif d'un collectif adopté
 *  - reduction         : mouvement de dépense négatif d'un collectif adopté
 *  - virement_entrant  : virement exécuté VERS la ligne (dans un collectif ou non)
 *  - virement_sortant  : virement exécuté DEPUIS la ligne (dans un collectif ou non)
 */
class HistoriqueLigneBudgetaireService
{
    public const LIBELLES = [
        'augmentation'     => 'Augmentation',
        'reduction'        => 'Réduction',
        'virement_entrant' => 'Virement +',
        'virement_sortant' => 'Virement −',
    ];

    /** Les sept colonnes du gel (migration 2026_11_04_000014) — toutes ou aucune. */
    public const COLONNES_GEL = [
        'snapshot_budget_initial',
        'snapshot_budget_rectifie',
        'snapshot_modifications',
        'snapshot_total_engage_avant',
        'snapshot_disponible_avant',
        'snapshot_disponible_apres',
        'snapshot_fige_le',
    ];

    /**
     * Ce qui défait le papier : changer de budget, de ligne, de jour ou de montant.
     * Le reste — observations, statut, validation, annulation — ne bouge pas l'état
     * que le signataire a vu, donc ne repose pas le gel.
     */
    public const CHAMPS_QUI_GELENT = [
        'budget_id',
        'nomenclature_principale_id',
        'date_engagement',
        'montant_engage',
    ];

    /**
     * @param  ?Carbon  $jusquau  Borne chronologique : null = état du jour (écrans,
     *         tableau de bord). Pour un document déjà émis, on passe Engagement::dateEmission()
     *         — sans quoi la ré-impression d'un papier signé y ferait apparaître un
     *         virement postérieur.
     * @return Collection<int, array{date: ?Carbon, nature: string, libelle_nature: string, montant: float,
     *         origine: string, reference: string, contrepartie: ?string, motif: ?string}>
     */
    public function modifications(LigneBudgetaire $ligne, ?Carbon $jusquau = null): Collection
    {
        if (!$ligne->id) {
            return collect();
        }

        return collect()
            ->merge($this->mouvementsCollectifs($ligne, $jusquau))
            ->merge($this->virements($ligne, $jusquau))
            ->sortBy(fn($m) => $m['date']?->timestamp ?? 0)
            ->values();
    }

    /** Synthèse : dotation initiale, total des modifications, dotation finale. */
    public function synthese(LigneBudgetaire $ligne, ?Carbon $jusquau = null): array
    {
        $modifications = $this->modifications($ligne, $jusquau);
        $initiale = (float) ($ligne->budget_initial ?? 0);
        $total = (float) $modifications->sum('montant');

        return [
            'dotation_initiale' => $initiale,
            'modifications'     => $modifications,
            'total'             => $total,
            'dotation_finale'   => $initiale + $total,
            'par_nature'        => collect(array_keys(self::LIBELLES))
                ->mapWithKeys(fn($n) => [$n => (float) $modifications->where('nature', $n)->sum('montant')])
                ->all(),
        ];
    }

    /**
     * Synthèse à l'échelle d'un ou plusieurs budgets (tableau de bord).
     * Mêmes règles que modifications() et LigneBudgetaire::getBudgetRectifieReel() :
     *   budget actualisé = initial + augmentations + réductions + virements entrants − sortants.
     */
    public function syntheseBudgets(iterable $budgetIds): array
    {
        $budgetIds = collect($budgetIds)->filter()->values();
        $ligneIds = LigneBudgetaire::whereIn('budget_id', $budgetIds)->pluck('id');

        $initial = (float) LigneBudgetaire::whereIn('budget_id', $budgetIds)->sum('budget_initial');

        $mouvements = MouvementCollectif::query()
            ->where('type', 'depense')
            ->where(fn($q) => $q->whereIn('ligne_depense_id', $ligneIds)->orWhereIn('nouvelle_ligne_depense_id', $ligneIds))
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'));

        $virements = VirementBudgetaire::query()
            ->where('statut', 'execute')
            ->where(fn($q) => $q->whereIn('ligne_source_id', $ligneIds)->orWhereIn('ligne_destination_id', $ligneIds));

        $augmentations = (float) (clone $mouvements)->where('montant_modification', '>', 0)->sum('montant_modification');
        $reductions = (float) (clone $mouvements)->where('montant_modification', '<', 0)->sum('montant_modification');
        $entrants = (float) (clone $virements)->whereIn('ligne_destination_id', $ligneIds)->sum('montant');
        $sortants = (float) (clone $virements)->whereIn('ligne_source_id', $ligneIds)->sum('montant');

        // ✅ Un collectif adopté peut ne faire QUE transvaser des crédits (mouvements
        //    de type « virement »). Compté uniquement sur les mouvements de ligne, il
        //    restait invisible au tableau de bord : « 0 FCFA · Aucun collectif adopté ».
        $mouvementsVirements = MouvementCollectif::query()
            ->where('type', 'virement')
            ->whereIn('virement_budgetaire_id', (clone $virements)->pluck('id'))
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'));

        $idsCollectifs = (clone $mouvements)->distinct()->pluck('collectif_budgetaire_id')
            ->merge($mouvementsVirements->distinct()->pluck('collectif_budgetaire_id'))
            ->filter()->unique();

        return [
            'budget_initial'      => $initial,
            'augmentations'       => $augmentations,
            'reductions'          => $reductions,          // négatif
            'collectifs_net'      => $augmentations + $reductions,
            'nb_augmentations'    => (clone $mouvements)->where('montant_modification', '>', 0)->count(),
            'nb_reductions'       => (clone $mouvements)->where('montant_modification', '<', 0)->count(),
            'nb_collectifs'       => $idsCollectifs->count(),
            'virements_montant'   => (float) (clone $virements)->sum('montant'),  // crédits déplacés
            'virements_nombre'    => (clone $virements)->count(),
            'virements_net'       => $entrants - $sortants,                     // 0 si interne au budget
            // Part des virements réellement issue d'un collectif : affichée par la carte
            // « Collectifs », le solde restant porté par la carte « Virements ».
            'virements_collectif_nombre'  => (clone $mouvementsVirements)->count(),
            'virements_collectif_montant' => (float) VirementBudgetaire::query()
                ->whereIn('id', (clone $mouvementsVirements)->pluck('virement_budgetaire_id')->filter())
                ->sum('montant'),
            'budget_actualise'    => $initial + $augmentations + $reductions + $entrants - $sortants,
        ];
    }

    // ────────────────────────────────────────────────────────────────

    /** Augmentations et réductions des collectifs adoptés (mouvements de dépense). */
    protected function mouvementsCollectifs(LigneBudgetaire $ligne, ?Carbon $jusquau = null): Collection
    {
        return MouvementCollectif::query()
            ->where('type', 'depense')
            ->where(fn($q) => $q->where('ligne_depense_id', $ligne->id)->orWhere('nouvelle_ligne_depense_id', $ligne->id))
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->with('collectif')
            ->get()
            // ✅ Collection ordinaire : une collection Eloquent VIDE ne peut pas être fusionnée
            //    avec des tableaux (erreur « getKey() on array » quand la ligne n'a que des virements)
            ->toBase()
            ->map(function (MouvementCollectif $m) {
                $montant = (float) $m->montant_modification;
                $nature = $montant >= 0 ? 'augmentation' : 'reduction';

                return [
                    'date'           => $this->date($m->collectif?->date_adoption ?? $m->created_at),
                    'nature'         => $nature,
                    'libelle_nature' => self::LIBELLES[$nature],
                    'montant'        => $montant,
                    'origine'        => 'Collectif budgétaire',
                    'reference'      => (string) ($m->collectif?->numero ?? '—'),
                    'contrepartie'   => null,
                    'motif'          => $m->motif,
                ];
            })
            ->filter(fn($m) => $this->auJourDuPapier($m, $jusquau))
            ->values();
    }

    /**
     * Virements exécutés vers ou depuis la ligne.
     * Un virement réalisé dans un collectif est rattaché à ce collectif (référence affichée).
     */
    protected function virements(LigneBudgetaire $ligne, ?Carbon $jusquau = null): Collection
    {
        $virements = VirementBudgetaire::query()
            ->where('statut', 'execute')
            ->where(fn($q) => $q->where('ligne_source_id', $ligne->id)->orWhere('ligne_destination_id', $ligne->id))
            ->with(['ligneSource.nomenclature', 'ligneDestination.nomenclature'])
            ->get();

        if ($virements->isEmpty()) {
            return collect();
        }

        // Virements issus d'un collectif : retrouver le collectif (et le motif du mouvement)
        $mouvements = MouvementCollectif::query()
            ->whereIn('virement_budgetaire_id', $virements->pluck('id'))
            ->with('collectif')
            ->get()
            ->keyBy('virement_budgetaire_id');

        return $virements->toBase()->map(function (VirementBudgetaire $v) use ($ligne, $mouvements) {
            $entrant = (int) $v->ligne_destination_id === (int) $ligne->id;
            $nature = $entrant ? 'virement_entrant' : 'virement_sortant';
            $autre = $entrant ? $v->ligneSource : $v->ligneDestination;
            $mouvement = $mouvements->get($v->id);

            return [
                'date'           => $this->date(
                    $mouvement?->collectif?->date_adoption
                        ?? $v->getAttribute('date_execution')
                        ?? $v->getAttribute('date_virement')
                        ?? $v->updated_at
                ),
                'nature'         => $nature,
                'libelle_nature' => self::LIBELLES[$nature],
                'montant'        => ($entrant ? 1 : -1) * (float) $v->montant,
                'origine'        => $mouvement ? 'Collectif budgétaire' : 'Virement budgétaire',
                'reference'      => (string) ($mouvement?->collectif?->numero
                    ?? $v->getAttribute('numero')
                    ?? $v->getAttribute('reference')
                    ?? ('#' . $v->id)),
                'contrepartie'   => $autre?->nomenclature?->code,
                'motif'          => $mouvement?->motif
                    ?? $v->getAttribute('motif')
                    ?? $v->getAttribute('objet')
                    ?? $v->getAttribute('observations'),
            ];
        })
            ->filter(fn($m) => $this->auJourDuPapier($m, $jusquau))
            ->values();
    }

    /**
     * Une modification regarde le papier quand elle est antérieure à sa borne. Une
     * modification non datable en sort : ce qu'on ne peut pas situer dans le temps ne
     * peut pas être attesté sur un document signé.
     */
    private function auJourDuPapier(array $modification, ?Carbon $jusquau): bool
    {
        return $jusquau === null
            || ($modification['date'] !== null && $modification['date']->lte($jusquau));
    }

    /**
     * L'état de la ligne TEL QU'UN DOCUMENT L'ATTESTE — source unique des trois
     * gabarits (certificat d'engagement, certificat avec impôts, ordonnance de
     * paiement), qui calculaient chacun leur propre version du « disponible ».
     *
     * Deux régimes, dans cet ordre :
     *  - le gel posé à l'émission (volet 2) : c'est la copie de ce que le papier dit, et
     *    elle ne se dément pas, quoi qu'il advienne ensuite d'un collectif ou d'un virement ;
     *  - à défaut, la reconstruction au jour du papier (volet 1) — pour les documents nés
     *    avant le gel et non encore backfillés.
     *
     * @return array{ligne: ?LigneBudgetaire, dotation_initiale: float, dotation_finale: float,
     *         modifications: Collection, virements_entrants: float, virements_sortants: float,
     *         total_engage_avant: float, disponible_avant: float, disponible_apres: float}
     */
    public function etatPourEngagement(?Engagement $engagement): array
    {
        return $this->estGele($engagement)
            ? $this->etatGele($engagement)
            : $this->etatAuJourDuPapier($engagement);
    }

    /** Un gel partiel n'est pas un gel : la colonne de tête le décide. */
    public function estGele(?Engagement $engagement): bool
    {
        return $engagement !== null && $engagement->snapshot_disponible_avant !== null;
    }

    protected function etatGele(Engagement $engagement): array
    {
        $etat = $this->etatVide();

        $modifications = collect($engagement->snapshot_modifications ?? [])
            ->map(function ($m) {
                $m = (array) $m;
                $m['date'] = $this->date($m['date'] ?? null);

                return $m;
            })
            ->sortBy(fn($m) => $m['date']?->timestamp ?? 0)
            ->values();

        $etat['ligne']               = $this->ligneDe($engagement);
        $etat['dotation_initiale']   = (float) $engagement->snapshot_budget_initial;
        $etat['dotation_finale']     = (float) $engagement->snapshot_budget_rectifie;
        $etat['total_engage_avant']  = (float) $engagement->snapshot_total_engage_avant;
        $etat['disponible_avant']    = (float) $engagement->snapshot_disponible_avant;
        $etat['disponible_apres']    = (float) $engagement->snapshot_disponible_apres;

        return $this->avecModifications($etat, $modifications);
    }

    /**
     * La reconstruction : ce que la base permet encore de dire du jour du papier.
     *
     * Ce qui se lit à la date d'émission de l'engagement, et non au jour de l'impression :
     *  - la dotation : initiale + Σ des modifications antérieures. Le `budget_rectifie`
     *    de la ligne est une somme en cours de vie, elle bouge à chaque virement ;
     *  - l'engagé avant : les engagements du même jour passent avant celui-ci dans
     *    l'ordre (date, identifiant) — l'ancien `id <` comparait des clés d'insertion,
     *    pas le calendrier ; un engagement soldé ou annulé depuis comptait ce jour-là,
     *    il compte donc encore ;
     *  - le disponible, qui en découle.
     */
    public function etatAuJourDuPapier(?Engagement $engagement): array
    {
        $etat = $this->etatVide();

        if (!$engagement || !$engagement->nomenclature_principale_id) {
            return $etat;
        }

        $ligne = $this->ligneDe($engagement);
        if (!$ligne) {
            return $etat;
        }

        $dateEmission  = $engagement->dateEmission();
        $initiale      = (float) ($ligne->budget_initial ?? $ligne->montant_initial ?? 0);
        $modifications = $this->modifications($ligne, $dateEmission);
        $dotation      = $initiale + (float) $modifications->sum('montant');

        $etat['ligne']               = $ligne;
        $etat['dotation_initiale']   = $initiale;
        $etat['dotation_finale']     = $dotation;

        $jour = $dateEmission->copy()->startOfDay();

        $engageAvant = (float) Engagement::withoutGlobalScope('exercice')->withTrashed()
            ->where('budget_id', $ligne->budget_id)
            ->where('nomenclature_principale_id', $ligne->nomenclature_id)
            ->where('id', '!=', $engagement->id)
            // Un engagement soldé, annulé ou retiré depuis était comptable le jour du
            // papier : il compte encore. (Un engagement « annulé » par
            // Engagement::annuler() est forceDelete() : lui est irrécupérable, c'est ce
            // que le gel posé par geler() doit couvrir.)
            ->whereIn('statut', ['provisoire', 'definitif', 'solde', 'annule'])
            ->where(fn($s) => $s->whereNull('date_annulation')
                ->orWhere('date_annulation', '>', $dateEmission))
            ->where(fn($s) => $s->whereNull('deleted_at')
                ->orWhere('deleted_at', '>', $dateEmission))
            // Le jour même, l'ordre d'enregistrement fait foi : les papiers dressés
            // après celui-ci le voyaient déjà.
            ->where(fn($s) => $s->where('date_engagement', '<', $jour)
                ->orWhere(function ($t) use ($jour, $engagement) {
                    $t->whereDate('date_engagement', $jour->toDateString())
                      ->where('id', '<', $engagement->id);
                }))
            ->sum('montant_engage');

        $etat['total_engage_avant'] = $engageAvant;
        $etat['disponible_avant']   = $dotation - $engageAvant;
        $etat['disponible_apres']   = $dotation - $engageAvant - (float) ($engagement->montant_engage ?? 0);

        return $this->avecModifications($etat, $modifications);
    }

    /**
     * Pose le gel sur le modèle, sans écrire : l'appelant enregistre.
     *
     * `forceFill()` et non `update()` : les colonnes de gel ne sont pas dans `$fillable`,
     * un formulaire ne doit pas pouvoir les dicter.
     *
     * @return bool false quand il n'y a rien à geler (ligne introuvable) — le document
     *         reste alors sur la reconstruction, sans gel à moitié écrit.
     */
    public function poserGel(Engagement $engagement): bool
    {
        $etat = $this->etatAuJourDuPapier($engagement);
        if (!$etat['ligne']) {
            return false;
        }

        $engagement->forceFill([
            'snapshot_budget_initial'      => $etat['dotation_initiale'],
            'snapshot_budget_rectifie'     => $etat['dotation_finale'],
            'snapshot_modifications'       => $etat['modifications']->all(),
            'snapshot_total_engage_avant'  => $etat['total_engage_avant'],
            'snapshot_disponible_avant'    => $etat['disponible_avant'],
            'snapshot_disponible_apres'    => $etat['disponible_apres'],
            'snapshot_fige_le'             => Carbon::now(),
        ]);

        return true;
    }

    /** Gel puis écriture silencieuse — pour les chemins hors sauvegarde en cours. */
    public function geler(Engagement $engagement): bool
    {
        if (!$this->poserGel($engagement)) {
            return false;
        }

        $engagement->saveQuietly();

        return true;
    }

    protected function ligneDe(Engagement $engagement): ?LigneBudgetaire
    {
        return LigneBudgetaire::where('budget_id', $engagement->budget_id)
            ->where('nomenclature_id', $engagement->nomenclature_principale_id)
            ->first();
    }

    private function etatVide(): array
    {
        return [
            'ligne'               => null,
            'dotation_initiale'   => 0.0,
            'dotation_finale'     => 0.0,
            'modifications'       => collect(),
            'virements_entrants'  => 0.0,
            'virements_sortants'  => 0.0,
            'total_engage_avant'  => 0.0,
            'disponible_avant'    => 0.0,
            'disponible_apres'    => 0.0,
        ];
    }

    private function avecModifications(array $etat, Collection $modifications): array
    {
        $etat['modifications']      = $modifications;
        $etat['virements_entrants'] = abs((float) $modifications->where('nature', 'virement_entrant')->sum('montant'));
        $etat['virements_sortants'] = abs((float) $modifications->where('nature', 'virement_sortant')->sum('montant'));

        return $etat;
    }

    protected function date($valeur): ?Carbon
    {
        return $valeur ? Carbon::parse($valeur) : null;
    }
}
