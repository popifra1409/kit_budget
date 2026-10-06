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
     * ✅ Préfixe de numérotation des décisions de ce type (comme le code des types d'engagement) :
     * le CODE du type, en majuscules, lettres et chiffres uniquement (ex. « OM » → OM26-00001).
     * Renvoie null si le code est vide ou trop long (> 6 caractères) : la règle de repli s'applique.
     */
    public function prefixeNumero(): ?string
    {
        $prefixe = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->code));

        return ($prefixe !== '' && strlen($prefixe) <= 6) ? $prefixe : null;
    }

    /**
     * Relation : Décisions administratives
     */
    public function decisionsAdministratives()
    {
        return $this->hasMany(DecisionAdministrative::class, 'type_decision_id');
    }
}
