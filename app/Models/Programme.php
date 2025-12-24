<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'annee',
        'actif',
    ];

    protected $casts = [
        'annee' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Objectif principal du programme
     */
    public function objectifsPrincipaux(): HasMany
    {
        return $this->hasMany(ObjectifPrincipal::class);
    }

    /**
     * Relation : Actions du programme
     */
    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    /**
     * Obtenir le budget total du programme (somme des AE de toutes les tâches)
     */
    public function getBudgetTotal(): float
    {
        $total = 0;

        foreach ($this->actions as $action) {
            foreach ($action->activites as $activite) {
                foreach ($activite->taches as $tache) {
                    $total += $tache->ae;
                }
            }
        }

        return $total;
    }
}
