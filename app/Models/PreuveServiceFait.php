<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preuve attendue pour une nature de service fait (obligatoire ou non). */
class PreuveServiceFait extends Model
{
    protected $table = 'preuves_service_fait';

    protected $fillable = ['nature_service_fait_id', 'libelle', 'obligatoire', 'ordre'];

    protected $casts = ['obligatoire' => 'boolean'];

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureServiceFait::class, 'nature_service_fait_id');
    }
}
