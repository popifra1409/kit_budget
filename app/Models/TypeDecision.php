<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeDecision extends Model
{
    use HasFactory;

    protected $table = 'types_decision';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'actif',
        'ordre',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    /**
     * Scope : Types actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Ordonné
     */
    public function scopeOrdonne($query)
    {
        return $query->orderBy('ordre')->orderBy('libelle');
    }

    /**
     * Relation : Décisions administratives
     */
    public function decisionsAdministratives()
    {
        return $this->hasMany(DecisionAdministrative::class, 'type_decision_id');
    }
}
