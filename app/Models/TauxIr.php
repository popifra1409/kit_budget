<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TauxIr extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'taux_ir';

    protected $fillable = [
        'regime_fiscal_id',
        'montant_min',
        'montant_max',
        'taux',
        'montant_fixe',
        'date_debut',
        'date_fin',
        'actif',
    ];

    protected $casts = [
        'montant_min' => 'decimal:2',
        'montant_max' => 'decimal:2',
        'taux' => 'decimal:2',
        'montant_fixe' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Régime fiscal
     */
    public function regimeFiscal(): BelongsTo
    {
        return $this->belongsTo(RegimeFiscal::class);
    }

    /**
     * Scope : Actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : En vigueur aujourd'hui
     */
    public function scopeEnVigueur($query, $date = null)
    {
        $date = $date ?? now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('date_debut')
                ->orWhere('date_debut', '<=', $date);
        })
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', $date);
            });
    }
}
