<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LignePrevisionRecette extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lignes_previsions_recettes';

    protected $fillable = [
        'prevision_recette_id',
        'nomenclature_id',
        'code_nomenclature',
        'libelle_nomenclature',
        'montant_prevu_initial',
        'montant_rectifie',
        'montant_recouvre',
        'ecart',
        'taux_recouvrement',
        'ordre',
        'actif',
        'observations',
        // Renseigner la provenance depuis un collectif passe par create() dans
        // MouvementsRelationManager ; sans ces clés, l'attribution était
        // silencieusement ignorée par la protection de mass assignment.
        'est_issue_collectif',
        'collectif_creation_id',
    ];

    protected $casts = [
        'montant_prevu_initial' => 'decimal:2',
        'montant_rectifie' => 'decimal:2',
        'montant_recouvre' => 'decimal:2',
        'ecart' => 'decimal:2',
        'taux_recouvrement' => 'decimal:2',
        'ordre' => 'integer',
        'actif' => 'boolean',
        'est_issue_collectif' => 'boolean',
    ];

    public function getLibelleAttribute()
    {
        return $this->nomenclature?->libelle ?? 'N/A';
    }

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * Prévision de recettes parente
     */
    public function previsionRecette(): BelongsTo
    {
        return $this->belongsTo(PrevisionRecette::class, 'prevision_recette_id');
    }

    /**
     * Nomenclature budgétaire (classe 7 - recettes)
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Prévisions mensuelles (fractionnement sur 12 mois)
     */
    public function previsionsMensuelles(): HasMany
    {
        return $this->hasMany(PrevisionRecetteMensuelle::class);
    }

    /**
     * Recettes réelles associées (via prévisions mensuelles)
     */
    public function recettesReelles()
    {
        return RecetteReelle::whereIn(
            'prevision_recette_mensuelle_id',
            $this->previsionsMensuelles()->pluck('id')
        );
    }

    public function getLibelleWithRecouvreAttribute()
    {
        $nom = $this->nomenclature;
        $libelle = $nom ? "{$nom->code} - {$nom->libelle}" : 'Sans nomenclature';
        $recouvre = number_format($this->montant_recouvre ?? 0, 0, ',', ' ');
        return "{$libelle} (Recouvré: {$recouvre} FCFA)";
    }

    // ====================================
    // CALCULS
    // ====================================

    /**
     * Calculer et mettre à jour le montant recouvré
     */
    public function calculerMontantRecouvre(): float
    {
        // Sommer les montants recouvrés de toutes les prévisions mensuelles
        $total = $this->previsionsMensuelles()->sum('montant_recouvre');

        $this->update(['montant_recouvre' => $total]);

        return $total;
    }

    /**
     * Créer automatiquement les 12 prévisions mensuelles
     */
    public function creerPrevisionsmensuelles(): void
    {
        PrevisionRecetteMensuelle::creerPrevisionsAnnuelles($this);
    }

    /**
     * Redistribuer le montant rectifié sur les 12 mois
     */
    public function redistribuerSur12Mois(): void
    {
        PrevisionRecetteMensuelle::redistribuerMontant($this, $this->montant_rectifie);

        // Recalculer les cumulés pour tous les mois
        $this->previsionsMensuelles()->each(function ($prevision) {
            $prevision->calculerCumules();
        });
    }

    /**
     * ✅ Aligne totalement les 12 mois de la ligne sur son montant rectifié :
     *   mois retirés à tort ranimés, mois manquants recréés, montant de chaque mois
     *   recalé, puis écart / taux / cumulés recalculés dans l'ordre des mois.
     *
     * Les recouvrements ne sont pas touchés : ils sont resynchronisés depuis les
     * recettes réelles enregistrées sur chaque mois.
     *
     * Les mois en suppression reviennent d'abord car la contrainte unique
     * (ligne, mois, année) n'est pas partielle : un mois retiré bloquerait
     * silencieusement sa recréation par l'upsert.
     */
    public function alignerMensuelles(): void
    {
        PrevisionRecetteMensuelle::onlyTrashed()
            ->where('ligne_prevision_recette_id', $this->id)
            ->get()
            ->each
            ->restore();

        PrevisionRecetteMensuelle::creerPrevisionsAnnuelles($this);

        PrevisionRecetteMensuelle::where('ligne_prevision_recette_id', $this->id)
            ->orderBy('mois')
            ->get()
            ->each
            ->recalculer();

        $this->refresh()->recalculer();
    }

    /**
     * Calculer l'écart
     */
    public function calculerEcart(): float
    {
        $ecart = $this->montant_recouvre - $this->montant_rectifie;
        $this->update(['ecart' => $ecart]);
        return $ecart;
    }

    /**
     * Calculer le taux de recouvrement
     */
    public function calculerTauxRecouvrement(): float
    {
        if ($this->montant_rectifie == 0) {
            $taux = 0;
        } else {
            $taux = ($this->montant_recouvre / $this->montant_rectifie) * 100;
        }

        $this->update(['taux_recouvrement' => $taux]);
        return $taux;
    }

    /**
     * ✅ AJOUT — Montant rectifié RÉEL, recalculé (même règle que les lignes de dépenses) :
     *   prévision initiale + Σ mouvements de recettes des collectifs ADOPTÉS, non annulés,
     *   portant sur cette ligne existante.
     *
     * Une ligne créée par un collectif (est_issue_collectif) a pour prévision initiale le
     * montant du mouvement qui l'a créée : ce mouvement n'est donc pas ajouté une seconde fois.
     *
     * Remplace l'ancien cumul (montant_rectifie += mouvement), qui doublait le montant à
     * chaque réapplication d'un collectif.
     */
    public function getMontantRectifieReel(): float
    {
        $mouvements = (float) MouvementCollectif::query()
            ->where('type', 'recette')
            ->where('ligne_recette_id', $this->id)
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->sum('montant_modification');

        return round((float) $this->montant_prevu_initial + $mouvements, 2);
    }

    /**
     * ✅ AJOUT — Aligne montant_rectifie sur le montant réel. L'écart et le taux de recouvrement
     * sont recalculés par saving(), et l'observateur redistribue les 12 prévisions mensuelles.
     * Renvoie true si le montant a changé.
     */
    public function recalculerRectifie(): bool
    {
        $reel = $this->getMontantRectifieReel();

        if (abs((float) $this->montant_rectifie - $reel) < 0.01) {
            return false;
        }

        $this->montant_rectifie = $reel;
        $this->save();

        return true;
    }

    /**
     * Recalculer tous les indicateurs
     */
    public function recalculer(): void
    {
        $this->calculerMontantRecouvre();
        $this->calculerEcart();
        $this->calculerTauxRecouvrement();
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    /**
     * Montant restant à recouvrer
     */
    public function getMontantRestantAttribute(): float
    {
        return max(0, $this->montant_rectifie - $this->montant_recouvre);
    }

    /**
     * Pourcentage de réalisation
     */
    public function getPourcentageRealisationAttribute(): float
    {
        return $this->taux_recouvrement;
    }

    /**
     * Est en surperformance (recouvrement > prévu)
     */
    public function getEstSurperformanceAttribute(): bool
    {
        return $this->montant_recouvre > $this->montant_rectifie;
    }

    /**
     * Est en sous-performance (recouvrement < prévu)
     */
    public function getEstSousperformanceAttribute(): bool
    {
        return $this->montant_recouvre < $this->montant_rectifie;
    }

    // ====================================
    // SCOPES
    // ====================================

    /**
     * Scope: Lignes actives
     */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope: Ordonner
     */
    public function scopeOrdre($query)
    {
        return $query->orderBy('ordre');
    }

    /**
     * Scope: Par nomenclature
     */
    public function scopeNomenclature($query, $nomenclatureId)
    {
        return $query->where('nomenclature_id', $nomenclatureId);
    }
    //     public function previsionRecette()
    // {
    //     return $this->belongsTo(PrevisionRecette::class);
    // }

    // ====================================
    // BOOT & OBSERVERS
    // ===================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ligne) {

            if ($ligne->nomenclature_id && !$ligne->code_nomenclature) {
                $nomenclature = \App\Models\NomenclatureBudgetaire::find($ligne->nomenclature_id);

                if ($nomenclature) {
                    $ligne->code_nomenclature = $nomenclature->code;
                    $ligne->libelle_nomenclature = $nomenclature->libelle;
                }
                if (!$nomenclature) {
                    throw new \RuntimeException('Nomenclature budgétaire introuvable');
                }
            }

            if ($ligne->montant_rectifie == 0) {
                $ligne->montant_rectifie = $ligne->montant_prevu_initial;
            }
        });

        static::saving(function ($ligne) {
            $ligne->ecart = $ligne->montant_recouvre - $ligne->montant_rectifie;

            $ligne->taux_recouvrement = $ligne->montant_rectifie == 0
                ? 0
                : ($ligne->montant_recouvre / $ligne->montant_rectifie) * 100;
        });
    }
}
