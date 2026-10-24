<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compte du plan comptable SYSCOHADA.
 *
 * Référentiel propre au module financier : les classes 6 et 7 restent portées
 * par NomenclatureBudgetaire (l'autorité budgétaire) et sont reliées ici par
 * nomenclature_budgetaire_id, sans être recopiées.
 */
class CompteComptable extends Model
{
    use HasFactory;

    protected $table = 'comptes_comptables';

    protected $fillable = [
        'code',
        'intitule',
        'classe',
        'niveau',
        'parent_id',
        'categorie',
        'sens_normal',
        'type_tiers',
        'imputable',
        'nomenclature_budgetaire_id',
        'note',
    ];

    protected $casts = [
        'imputable' => 'boolean',
    ];

    public const NIVEAU_CLASSE       = 'classe';
    public const NIVEAU_GROUPE       = 'groupe';
    public const NIVEAU_COMPTE       = 'compte';
    public const NIVEAU_SOUS_COMPTE  = 'sous_compte';

    public const NIVEAUX = [
        self::NIVEAU_CLASSE,
        self::NIVEAU_GROUPE,
        self::NIVEAU_COMPTE,
        self::NIVEAU_SOUS_COMPTE,
    ];

    public const CATEGORIES = [
        'actif', 'passif', 'charge', 'produit', 'resultat',
        'contre_actif', 'contre_passif',
    ];

    public const SENS = ['debit', 'credit'];

    /**
     * Familles de tiers Recognues par le rattachement automatique.
     */
    public const TYPES_TIERS = [
        'fournisseur', 'client', 'personnel', 'organisme_social',
        'etat', 'associe', 'divers', 'hao', 'depreciation',
    ];

    /**
     * Compte de tiers par défaut d'un modèle de tiers du budget.
     */
    public const TYPE_TIERS_PAR_MODELE = [
        Fournisseur::class   => 'fournisseur',
        Personnel::class     => 'personnel',
        TiersRecette::class  => 'client',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lignesEcritures(): HasMany
    {
        return $this->hasMany(LigneEcriture::class, 'compte_comptable_id');
    }

    public function rattachementsTiers(): HasMany
    {
        return $this->hasMany(RattachementTiers::class, 'compte_comptable_id');
    }

    /**
     * Ligne de nomenclature budgétaire équivalente (classes 6 et 7).
     */
    public function nomenclatureBudgetaire(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_budgetaire_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeClasse(Builder $query, string $classe): Builder
    {
        return $query->where('classe', $classe);
    }

    public function scopeRacines(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeImputables(Builder $query): Builder
    {
        return $query->where('imputable', true);
    }

    public function scopeSousClasse(Builder $query, string $classe): Builder
    {
        return $query->where('classe', $classe)
            ->where('niveau', '!=', self::NIVEAU_CLASSE)
            ->orderBy('code');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors / Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Libellé hiérarchique : « Classe 4 — Comptes de tiers / Fournisseurs… ».
     */
    public function chemin(int $max = 3): string
    {
        $maillons = [$this->intitule];
        $parent   = $this->parent;
        $garde    = 0;

        while ($parent && $garde++ < $max) {
            array_unshift($maillons, $parent->intitule);
            $parent = $parent->parent;
        }

        return implode(' / ', $maillons);
    }

    /**
     * Le solde est lu en débit − crédit : un compte d'actif est positif, un
     * compte de passif est négatif dans cette présentation.
     */
    public function solde(): float
    {
        return (float) $this->lignesEcritures()
            ->selectRaw('coalesce(sum(debit), 0) - coalesce(sum(credit), 0) as solde')
            ->value('solde');
    }

    public function estFeuille(): bool
    {
        return $this->enfants()->doesntExist();
    }

    public static function parCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Compte de tiers par défaut d'une famille (401 fournisseur, 421 personnel,
     * 411 client) : le plus petit code imputable de la famille.
     */
    public static function compteDefautTiers(string $typeTiers): ?self
    {
        return static::where('type_tiers', $typeTiers)
            ->where('imputable', true)
            ->orderBy('code')
            ->first();
    }
}
