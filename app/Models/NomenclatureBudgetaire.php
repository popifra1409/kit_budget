<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomenclatureBudgetaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nomenclature_budgetaire';

    protected $fillable = [
        'code',
        'libelle',
        'classe',
        'type',
        'niveau',
        'parent_id',
        'date_debut_validite',
        'date_fin_validite',
        'code_precedent',
        'version_precedente_id',
        'version',
        'motif_modification',
        'modifie_par',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'date_debut_validite' => 'date',
        'date_fin_validite' => 'date',
        'actif' => 'boolean',
        'version' => 'integer',
        'ordre' => 'integer',
    ];

    /**
     * Relation : Parent dans la hiérarchie
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'parent_id');
    }

    /**
     * Relation : Enfants dans la hiérarchie
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(NomenclatureBudgetaire::class, 'parent_id');
    }

    /**
     * Relation : Version précédente
     */
    public function versionPrecedente(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'version_precedente_id');
    }

    /**
     * Relation : Versions ultérieures
     */
    public function versionsUlterieures(): HasMany
    {
        return $this->hasMany(NomenclatureBudgetaire::class, 'version_precedente_id');
    }

    /**
     * Relation : Utilisateur ayant modifié
     */
    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    /**
     * Scope : Nomenclatures actives (en cours)
     */
    public function scopeActives($query)
    {
        return $query->whereNull('date_fin_validite')
            ->where('actif', true);
    }

    /**
     * Scope : Nomenclatures valides à une date donnée
     */
    public function scopeValidesA($query, $date)
    {
        return $query->where('date_debut_validite', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('date_fin_validite')
                    ->orWhere('date_fin_validite', '>=', $date);
            });
    }

    /**
     * Scope : Par type (depense/recette)
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope : Par classe (6/7)
     */
    public function scopeClasse($query, $classe)
    {
        return $query->where('classe', $classe);
    }

    /**
     * Vérifier si cette nomenclature est en cours de validité
     */
    public function estEnCours(): bool
    {
        return is_null($this->date_fin_validite);
    }

    /**
     * Obtenir le chemin hiérarchique complet
     */
    public function getCheminComplet(): string
    {
        $chemin = [$this->libelle];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($chemin, $parent->libelle);
            $parent = $parent->parent;
        }

        return implode(' > ', $chemin);
    }
}
