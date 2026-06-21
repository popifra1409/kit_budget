<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieArticle extends Model
{
    protected $table = 'categories_article';

    protected $fillable = ['libelle', 'code', 'est_pharmacie', 'actif'];

    protected $casts = [
        'est_pharmacie' => 'boolean',
        'actif'         => 'boolean',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'categorie_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
