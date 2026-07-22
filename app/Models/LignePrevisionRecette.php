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
    ];

    protected $casts = [
        'montant_prevu_initial' => 'decimal:2',
        'montant_rectifie' => 'decimal:2',
        'montant_recouvre' => 'decimal:2',
        'ecart' => 'decimal:2',
        'taux_recouvrement' => 'decimal:2',
        'ordre' => 'integer',
        'actif' => 'boolean',
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
