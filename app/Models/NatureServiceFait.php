<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nature de service fait (médicaments, travaux, prestations...) et preuves attendues. */
class NatureServiceFait extends Model
{
    protected $table = 'natures_service_fait';

    protected $fillable = ['code', 'libelle', 'description', 'ordre', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function preuves(): HasMany
    {
        return $this->hasMany(PreuveServiceFait::class)->orderBy('ordre');
    }

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }
}
