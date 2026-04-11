<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'nom',
        'description',
        'parent_id',
        'responsable',
        'email',
        'telephone',
        'batiment',
        'bureau',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation : Service parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    /**
     * Relation : Services enfants (sous-services)
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id');
    }

    /**
     * Relation : Tâches dont ce service est responsable
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class, 'service_id');
    }

    /**
     * Relation : Bons de commande demandés par ce service
     */
    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class, 'service_demandeur_id');
    }

    /**
     * Scope : Services actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Services racines (sans parent)
     */
    public function scopeRacines($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Obtenir le chemin hiérarchique complet
     */
    public function getCheminComplet(): string
    {
        $chemin = [$this->nom];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($chemin, $parent->nom);
            $parent = $parent->parent;
        }

        return implode(' > ', $chemin);
    }

    /**
     * Obtenir tous les services descendants
     */
    public function getTousEnfants()
    {
        $enfants = collect();

        foreach ($this->enfants as $enfant) {
            $enfants->push($enfant);
            $enfants = $enfants->merge($enfant->getTousEnfants());
        }

        return $enfants;
    }
}
