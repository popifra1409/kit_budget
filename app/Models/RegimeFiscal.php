<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegimeFiscal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'regimes_fiscaux';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'ca_min',
        'ca_max',
        'taux_ir_defaut',
        'type_calcul_ir',
        'actif',
        'ordre',
    ];

    protected $casts = [
        'ca_min' => 'decimal:2',
        'ca_max' => 'decimal:2',
        'taux_ir_defaut' => 'decimal:2',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Taux IR
     */
    public function tauxIr(): HasMany
    {
        return $this->hasMany(TauxIr::class);
    }

    /**
     * Relation : Fournisseurs
     */
    public function fournisseurs(): HasMany
    {
        return $this->hasMany(Fournisseur::class);
    }

    /**
     * Scope : Actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /**
     * Calculer l'IR pour un montant donné
     */
    public function calculerIR(float $montantHT): float
    {
        switch ($this->type_calcul_ir) {
            case 'pourcentage':
                return $montantHT * ($this->taux_ir_defaut / 100);

            case 'forfaitaire':
                return $this->taux_ir_defaut;

            case 'tranche':
                // Chercher le taux applicable selon les tranches
                $tauxApplicable = $this->tauxIr()
                    ->where('actif', true)
                    ->where(function ($q) use ($montantHT) {
                        $q->where('montant_min', '<=', $montantHT)
                            ->orWhereNull('montant_min');
                    })
                    ->where(function ($q) use ($montantHT) {
                        $q->where('montant_max', '>=', $montantHT)
                            ->orWhereNull('montant_max');
                    })
                    ->first();

                if ($tauxApplicable) {
                    if ($tauxApplicable->taux) {
                        return $montantHT * ($tauxApplicable->taux / 100);
                    }
                    return $tauxApplicable->montant_fixe ?? 0;
                }

                return 0;

            default:
                return 0;
        }
    }
}
