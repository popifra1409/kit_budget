<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Programme extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $fillable = [
        'exercice_id',
        'parent_id',
        'niveau',
        'code',
        'libelle',
        'description',
        'annee',
        'actif',
    ];

    protected $casts = [
        'niveau' => 'string',
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

    /**
     * Relation : Programme parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'parent_id');
    }

    /**
     * Relation : Sous-programmes
     */
    public function sousProgrammes(): HasMany
    {
        return $this->hasMany(Programme::class, 'parent_id')->where('niveau', 'sous_programme');
    }

    /**
     * Relation : Enfants (programmes ou sous-programmes)
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Programme::class, 'parent_id');
    }

    /**
     * Scope : Programmes principaux (sans parent)
     */
    public function scopeProgrammesPrincipaux($query)
    {
        return $query->whereNull('parent_id')->where('niveau', 'programme');
    }

    /**
     * Scope : Sous-programmes uniquement
     */
    public function scopeSousProgrammes($query)
    {
        return $query->where('niveau', 'sous_programme');
    }

    /**
     * Vérifier si c'est un programme principal
     */
    public function estProgrammePrincipal(): bool
    {
        return $this->niveau === 'programme' && is_null($this->parent_id);
    }

    /**
     * Vérifier si c'est un sous-programme
     */
    public function estSousProgramme(): bool
    {
        return $this->niveau === 'sous_programme';
    }

    /**
     * Obtenir le chemin hiérarchique
     */
    public function getCheminComplet(): string
    {
        if ($this->estProgrammePrincipal()) {
            return $this->code . ' - ' . $this->libelle;
        }

        return ($this->parent ? $this->parent->code . ' > ' : '') . $this->code . ' - ' . $this->libelle;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'date_bordereau', 'montant_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Bordereau {$eventName}");
    }
}
