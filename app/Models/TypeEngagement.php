<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeEngagement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'types_engagement';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'montant_min',
        'montant_max',
        'mode_calcul_ir',
        'taux_ir_fixe',
        'taux_ir_regime_reel',
        'taux_ir_regime_simplifie',
        'actif',
        'ordre',
        'metadata',
    ];

    protected $casts = [
        'montant_min' => 'decimal:2',
        'montant_max' => 'decimal:2',
        'taux_ir_fixe' => 'decimal:2',
        'taux_ir_regime_reel' => 'decimal:2',
        'taux_ir_regime_simplifie' => 'decimal:2',
        'actif' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relation : Bons de commande
     */
    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    /**
     * Scope : Actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /**
     * Calculer le taux IR applicable
     */
    public function calculerTauxIR(?RegimeFiscal $regimeFiscal = null): float
    {
        switch ($this->mode_calcul_ir) {
            case 'fixe':
                return $this->taux_ir_fixe ?? 0;

            case 'selon_regime':
                if (!$regimeFiscal) {
                    return 0;
                }

                return match ($regimeFiscal->code) {
                    'REEL' => $this->taux_ir_regime_reel ?? 0,
                    'SIMPLIFIE' => $this->taux_ir_regime_simplifie ?? 0,
                    default => 0,
                };

            case 'aucun':
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Calculer l'IR pour un montant donné
     */
    public function calculerIR(float $montantHT, ?RegimeFiscal $regimeFiscal = null): float
    {
        $taux = $this->calculerTauxIR($regimeFiscal);
        return $montantHT * ($taux / 100);
    }

    /**
     * Vérifier si un montant correspond à ce type
     */
    public function montantCorrespond(float $montant): bool
    {
        $minOk = is_null($this->montant_min) || $montant >= $this->montant_min;
        $maxOk = is_null($this->montant_max) || $montant < $this->montant_max;

        return $minOk && $maxOk;
    }

    /**
     * Déterminer automatiquement le type d'engagement selon le montant
     */
    public static function determinerParMontant(float $montant): ?self
    {
        return static::actifs()
            ->where(function ($query) use ($montant) {
                $query->where(function ($q) use ($montant) {
                    $q->where('montant_min', '<=', $montant)
                        ->orWhereNull('montant_min');
                })
                    ->where(function ($q) use ($montant) {
                        $q->where('montant_max', '>', $montant)
                            ->orWhereNull('montant_max');
                    });
            })
            ->first();
    }
}
