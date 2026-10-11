<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Ligne de mini-budget d'une régie = UN ENCAISSE (une tranche de décaissement)
 * sur une nomenclature. La DA fournit l'enveloppe annuelle, la tranche fournit
 * la ligne, son alloué et son taux de consommation.
 */
class LigneRegieAvance extends Model
{
    protected $table = 'lignes_regies_avances';

    protected $fillable = [
        'regie_avance_id',
        'decaissement_regie_id',
        'nomenclature_id',
        'ligne_budgetaire_id',
        'montant_alloue',
        'montant_consomme',
        'montant_disponible',
    ];

    protected $casts = [
        'montant_alloue'     => 'decimal:2',
        'montant_consomme'   => 'decimal:2',
        'montant_disponible' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($ligne) {
            $ligne->montant_disponible = $ligne->montant_alloue;
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function decaissementRegie(): BelongsTo
    {
        return $this->belongsTo(DecaissementRegie::class, 'decaissement_regie_id');
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_budgetaire_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'ligne_regie_avance_id');
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(ProvisionLigneRegie::class, 'ligne_regie_avance_id');
    }

    // ── Méthodes ──────────────────────────────────────────────

    /**
     * Ce que CET encaisse a réellement consommé : la somme des débits portés par
     * ses provisions (engagements BCR en cascade + achats directs validés), plus
     * les achats directs anciens qui n'ont pas de provision.
     *
     * Avant : le TTC complet de chaque BCR engagé, ce qui comptait double une
     * consommation déjà tracer sur la provision et mélangeait les encaisses —
     * d'où les taux > 100 % sans dépense réelle correspondante.
     */
    public function montantConsommeReel(): float
    {
        $surProvisions = (float) ProvisionLigneRegie::where('ligne_regie_avance_id', $this->id)
            ->sum('montant_consomme');

        $sansProvision = (float) DepenseRegie::where('ligne_regie_avance_id', $this->id)
            ->whereNull('provision_ligne_regie_id')
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        return $surProvisions + $sansProvision;
    }

    public function recalculerMontants(): void
    {
        $consomme = $this->montantConsommeReel();

        $this->updateQuietly([
            'montant_consomme'   => $consomme,
            'montant_disponible' => (float) $this->montant_alloue - $consomme,
        ]);
    }

    /**
     * Les encaisses consommables pour cette nomenclature : toutes les lignes de
     * la régie, du plus ancien encaisse versé au plus récent. Un bon de commande
     * épuise le 1er encaisse puis déborde sur le suivant ; ce n'est qu'au-delà du
     * dernier qu'il faut approvisionner la régie.
     */
    public function poolEncaisses(): Collection
    {
        return static::where('regie_avance_id', $this->regie_avance_id)
            ->where('nomenclature_id', $this->nomenclature_id)
            ->with(['decaissementRegie', 'nomenclature'])
            ->get()
            ->sortBy(fn($l) => [
                $l->decaissementRegie?->date_decaissement?->format('Ymd') ?? '99999999',
                $l->decaissementRegie?->numero ?? '',
                $l->id,
            ])
            ->values();
    }

    /**
     * Les provisions à drainer dans l'ordre des encaisses.
     */
    public function provisionsConsommables(): Collection
    {
        $lignes = $this->poolEncaisses();

        $groupees = ProvisionLigneRegie::whereIn('ligne_regie_avance_id', $lignes->pluck('id'))
            ->whereHas('decaissement', fn($q) => $q->where('statut', 'verse'))
            ->get()
            ->groupBy('ligne_regie_avance_id');

        return $lignes
            ->flatMap(fn($l) => $groupees->get($l->id, collect()))
            ->values();
    }

    /**
     * Enveloppe réellement consommable : la somme des positiveités de chaque
     * encaisse. Un encaisse historiquement en négatif ne doit pas réduire ce
     * qu'un AUTRE encaisse a de disponible.
     */
    public function resumeEncaisses(): array
    {
        $resume = ['alloue' => 0.0, 'consomme' => 0.0, 'disponible' => 0.0, 'encaisses' => []];

        foreach ($this->poolEncaisses() as $ligne) {
            $consomme = $ligne->montantConsommeReel();
            $dispo    = max(0, (float) $ligne->montant_alloue - $consomme);

            $resume['alloue']     += (float) $ligne->montant_alloue;
            $resume['consomme']   += $consomme;
            $resume['disponible'] += $dispo;

            $resume['encaisses'][] = [
                'numero'   => $ligne->decaissementRegie?->numero ?? 'hors tranche',
                'libelle'  => $ligne->decaissementRegie?->libelle_tranche ?? 'ligne sans encaisse',
                'alloue'   => (float) $ligne->montant_alloue,
                'consomme' => $consomme,
                'dispo'    => $dispo,
            ];
        }

        return $resume;
    }

    /**
     * Blocage dur : on ne peut pas consommer plus que ce que la régie a encaissé
     * sur cette nomenclature. Au-delà, la seule issue est un nouvel encaisse.
     */
    public function controlerPlafond(float $montant): void
    {
        $resume = $this->resumeEncaisses();

        if ($montant <= $resume['disponible'] + 0.01) {
            return;
        }

        $detail = '';
        foreach ($resume['encaisses'] as $e) {
            $detail .= '  • ' . $e['numero'] . ' « ' . $e['libelle'] . ' » : alloué '
                . number_format($e['alloue'], 0, ',', ' ') . ', consommé '
                . number_format($e['consomme'], 0, ',', ' ') . ', disponible '
                . number_format($e['dispo'], 0, ',', ' ') . " FCFA\n";
        }

        throw new \Exception(
            "⛔ Les encaisses de la régie sont épuisés — veuillez approvisionner la régie.\n"
                . 'Nomenclature ' . ($this->nomenclature?->code ?? 'sans code') . " :\n"
                . 'Alloué par les encaisses : ' . number_format($resume['alloue'], 0, ',', ' ') . " FCFA\n"
                . 'Déjà consommé : ' . number_format($resume['consomme'], 0, ',', ' ') . " FCFA\n"
                . 'Disponible : ' . number_format($resume['disponible'], 0, ',', ' ') . " FCFA\n"
                . 'Montant demandé : ' . number_format($montant, 0, ',', ' ')
                . ' — il manque ' . number_format($montant - $resume['disponible'], 0, ',', ' ') . " FCFA\n"
                . $detail
                . 'Solution : enregistrer une nouvelle DA sur la régie, puis « Accorder & Verser » '
                . 'une tranche — elle créera sa propre ligne de mini-budget.'
        );
    }

    public function getTauxConsommationAttribute(): float
    {
        if ($this->montant_alloue <= 0) return 0;
        return round(($this->montant_consomme / $this->montant_alloue) * 100, 2);
    }
}
