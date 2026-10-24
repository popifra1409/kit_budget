<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Journal comptable : famille d'écritures et tête de leur numéro.
 */
class JournalComptable extends Model
{
    use HasFactory;

    protected $table = 'journaux_comptables';

    protected $fillable = [
        'code',
        'libelle',
        'type',
        'prefixe_numero',
        'description',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public const TYPES = [
        'achats', 'vente', 'tresorerie', 'recettes', 'paiements',
        'engagement', 'operations_diverses',
    ];

    public function ecritures(): HasMany
    {
        return $this->hasMany(EcritureComptable::class, 'journal_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public static function parCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
