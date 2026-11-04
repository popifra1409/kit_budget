<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Type de contrat d'un marché, déterminé par le seuil du montant (paramétrage).
 *
 * Chaque ligne est un test « montant operateur seuil_min ». L'arbitrage est
 * séquentiel : la première ligne active qui réussit, dans l'ordre de « ordre ».
 */
class TypeContrat extends Model
{
    protected $table = 'types_contrat';

    protected $fillable = ['code', 'libelle', 'seuil_min', 'operateur', 'description', 'ordre', 'actif'];

    protected $casts = [
        'actif'     => 'boolean',
        'seuil_min' => 'decimal:2',
    ];

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /** Le contrat attendu pour un montant. Null si le paramétrage ne couvre pas ce montant. */
    public static function pourMontant(float $montant): ?self
    {
        return static::actives()->get()->first(fn(self $t) => $t->correspond($montant));
    }

    public function correspond(float $montant): bool
    {
        return $this->operateur === 'lt'
            ? $montant < (float) $this->seuil_min
            : $montant >= (float) $this->seuil_min;
    }
}
