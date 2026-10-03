<?php
// app/Services/Budget/HistoriqueLigneBudgetaireService.php

namespace App\Services\Budget;

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

    /**
     * @return Collection<int, array{date: ?Carbon, nature: string, libelle_nature: string, montant: float,
     *         origine: string, reference: string, contrepartie: ?string, motif: ?string}>
     */
    public function modifications(LigneBudgetaire $ligne): Collection
    {
        if (!$ligne->id) {
            return collect();
        }

        return collect()
            ->merge($this->mouvementsCollectifs($ligne))
            ->merge($this->virements($ligne))
            ->sortBy(fn($m) => $m['date']?->timestamp ?? 0)
            ->values();
    }

    /** Synthèse : dotation initiale, total des modifications, dotation finale. */
    public function synthese(LigneBudgetaire $ligne): array
    {
        $modifications = $this->modifications($ligne);
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

    // ────────────────────────────────────────────────────────────────

    /** Augmentations et réductions des collectifs adoptés (mouvements de dépense). */
    protected function mouvementsCollectifs(LigneBudgetaire $ligne): Collection
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
            });
    }

    /**
     * Virements exécutés vers ou depuis la ligne.
     * Un virement réalisé dans un collectif est rattaché à ce collectif (référence affichée).
     */
    protected function virements(LigneBudgetaire $ligne): Collection
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
        });
    }

    protected function date($valeur): ?Carbon
    {
        return $valeur ? Carbon::parse($valeur) : null;
    }
}
