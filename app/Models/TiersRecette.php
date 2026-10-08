<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Débiteur / payeur des recettes (caisse, État, assureur, société, donateur…). */
class TiersRecette extends Model
{
    use SoftDeletes;

    protected $table = 'tiers_recettes';

    protected $fillable = ['nom', 'categorie', 'telephone', 'email', 'adresse', 'observations', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public const CATEGORIES = [
        'caisse'      => 'Caisse',
        'subvention'  => 'Subvention (État, collectivités)',
        'dons_legs'   => 'Dons et legs',
        'assurance'   => 'Assurance',
        'societe'     => 'Société / entreprise',
        'convention'  => 'Organisme conventionné',
        'particulier' => 'Particulier',
        'autre'       => 'Autre',
    ];

    public function recettes(): HasMany
    {
        return $this->hasMany(RecetteReelle::class, 'tiers_recette_id');
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('nom');
    }

    public function getCategorieLabelAttribute(): string
    {
        return self::CATEGORIES[$this->categorie] ?? $this->categorie;
    }

    /** Options groupées par catégorie, pour les listes déroulantes. */
    public static function optionsGroupees(): array
    {
        return static::actifs()->get()
            ->groupBy(fn($t) => self::CATEGORIES[$t->categorie] ?? $t->categorie)
            ->map(fn($groupe) => $groupe->pluck('nom', 'id')->all())
            ->all();
    }
}
