<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GroupeNomenclature extends Model
{
    use HasFactory;

    /**
     * Table associée (optionnel, Laravel la déduit déjà correctement,
     * mais on le laisse explicite pour la lisibilité)
     */
    protected $table = 'groupes_nomenclature';

    /**
     * Champs assignables en masse
     */
    protected $fillable = [
        'code',
        'libelle',
        'type',
        'description',
        'ordre',
        'actif',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    /**
     * Constantes pour le champ type (évite les "magic strings")
     */
    public const TYPE_DEPENSE = 'depense';
    public const TYPE_RECETTE = 'recette';

    public const TYPES = [
        self::TYPE_DEPENSE,
        self::TYPE_RECETTE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Lignes de nomenclature budgétaire rattachées à ce groupe
     */
    public function lignesNomenclature(): HasMany
    {
        return $this->hasMany(NomenclatureBudgetaire::class, 'groupe_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filtre uniquement les groupes actifs
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /**
     * Filtre par type (depense / recette)
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Filtre les groupes de dépenses
     */
    public function scopeDepenses(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEPENSE);
    }

    /**
     * Filtre les groupes de recettes
     */
    public function scopeRecettes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_RECETTE);
    }

    /**
     * Tri par ordre défini (utile pour l'affichage dans l'UI)
     */
    public function scopeOrdonne(Builder $query): Builder
    {
        return $query->orderBy('ordre');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors / Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Nombre de lignes de nomenclature rattachées à ce groupe
     */
    public function getNombreLignesAttribute(): int
    {
        return $this->lignesNomenclature()->count();
    }

    /**
     * Vérifie si le groupe peut être supprimé (aucune ligne rattachée)
     */
    public function estSupprimable(): bool
    {
        return $this->lignesNomenclature()->doesntExist();
    }
}
