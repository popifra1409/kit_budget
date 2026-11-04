<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Procédure de passation d'un marché (paramétrage).
 * Feuille saisie sur le marché ; « famille » ne sert qu'au regroupement affiché.
 */
class TypeProcedure extends Model
{
    protected $table = 'types_procedure';

    protected $fillable = ['code', 'libelle', 'famille', 'description', 'ordre', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }
}
