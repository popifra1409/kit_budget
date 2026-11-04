<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catégorie du marché : pluriannuel, annuel, mensuel (paramétrage). */
class CategorieMarche extends Model
{
    protected $table = 'categories_marche';

    protected $fillable = ['code', 'libelle', 'duree_mois', 'pluri_annuel', 'description', 'ordre', 'actif'];

    protected $casts = [
        'actif'        => 'boolean',
        'pluri_annuel' => 'boolean',
        'duree_mois'   => 'integer',
    ];

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }
}
