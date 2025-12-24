<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CadreLogique extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cadre_logique';

    protected $fillable = [
        'code',
        'libelle',
        'niveau',
        'parent_id',
        'indicateurs_resultat',
        'budget_alloue',
        'annee',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'indicateurs_resultat' => 'array',
        'budget_alloue' => 'decimal:2',
        'actif' => 'boolean',
        'annee' => 'integer',
        'ordre' => 'integer',
    ];

    /**
     * Relation : Parent dans la hiérarchie
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CadreLogique::class, 'parent_id');
    }

    /**
     * Relation : Enfants dans la hiérarchie
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(CadreLogique::class, 'parent_id');
    }

    /**
     * Relation : Nomenclatures budgétaires liées
     */
    public function nomenclatures(): BelongsToMany
    {
        return $this->belongsToMany(
            NomenclatureBudgetaire::class,
            'nomenclature_cadre_logique',
            'cadre_logique_id',
            'nomenclature_id'
        )->withPivot('niveau_liaison', 'montant_affecte')
            ->withTimestamps();
    }

    /**
     * Scope : Par niveau
     */
    public function scopeNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    /**
     * Scope : Par année
     */
    public function scopeAnnee($query, $annee)
    {
        return $query->where('annee', $annee);
    }

    /**
     * Scope : Actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Programmes uniquement
     */
    public function scopeProgrammes($query)
    {
        return $query->where('niveau', 'programme');
    }

    /**
     * Scope : Actions uniquement
     */
    public function scopeActions($query)
    {
        return $query->where('niveau', 'action');
    }

    /**
     * Scope : Activités uniquement
     */
    public function scopeActivites($query)
    {
        return $query->where('niveau', 'activite');
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

    /**
     * Obtenir le libellé du niveau en français
     */
    public function getNiveauLibelle(): string
    {
        return match ($this->niveau) {
            'programme' => 'Programme',
            'objectif_general' => 'Objectif Général',
            'action' => 'Action',
            'objectif_specifique' => 'Objectif Spécifique',
            'activite' => 'Activité',
            default => $this->niveau,
        };
    }

    /**
     * Calculer le budget total des enfants
     */
    public function getBudgetTotal(): float
    {
        $total = $this->budget_alloue ?? 0;

        foreach ($this->enfants as $enfant) {
            $total += $enfant->getBudgetTotal();
        }

        return $total;
    }
}
