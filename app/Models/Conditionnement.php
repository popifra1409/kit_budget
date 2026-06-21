<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conditionnement extends Model
{
    protected $table = 'conditionnements';

    protected $fillable = ['libelle', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function lignesExpressionBesoins(): HasMany
    {
        return $this->hasMany(LigneExpressionBesoin::class);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
