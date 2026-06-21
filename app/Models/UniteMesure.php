<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UniteMesure extends Model
{
    protected $table = 'unites_mesure';

    protected $fillable = ['libelle', 'symbole', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
