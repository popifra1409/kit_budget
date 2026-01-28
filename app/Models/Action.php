<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Action extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'actions';

    protected $fillable = [
        'exercice_id',
        'programme_id',
        'code',
        'libelle',
        'description',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Programme parent
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    /**
     * Relation : Objectifs spécifiques de l'action
     */
    public function objectifsSpecifiques(): HasMany
    {
        return $this->hasMany(ObjectifSpecifique::class);
    }

    /**
     * Relation : Activités de l'action
     */
    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    /**
     * Obtenir le budget total de l'action (ancienne méthode - conservée pour compatibilité)
     * @deprecated Utilisez getTotalAe() à la place
     */
    public function getBudgetTotal(): float
    {
        $total = 0;

        foreach ($this->activites as $activite) {
            foreach ($activite->taches as $tache) {
                $total += $tache->ae;
            }
        }

        return $total;
    }

    /**
     * Calculer le total AE de toutes les activités de cette action
     * 
     * Cette méthode fait la somme des AE de toutes les activités,
     * qui elles-mêmes font la somme de leurs tâches principales,
     * qui elles-mêmes font la somme de leurs sous-tâches.
     * 
     * Hiérarchie : Action > Activités > Tâches > Sous-tâches
     * 
     * @return float Total des autorisations d'engagement
     */
    public function getTotalAe(): float
    {
        // Si les activités ne sont pas chargées, utiliser une requête
        if (!$this->relationLoaded('activites')) {
            return (float) $this->activites()
                ->get()
                ->sum(function ($activite) {
                    return $activite->getTotalAe();
                });
        }

        // Si les activités sont déjà chargées, utiliser la collection
        return (float) $this->activites->sum(function ($activite) {
            return $activite->getTotalAe();
        });
    }

    /**
     * Calculer le total CP de toutes les activités de cette action
     * 
     * @return float Total des crédits de paiement
     */
    public function getTotalCp(): float
    {
        // Si les activités ne sont pas chargées, utiliser une requête
        if (!$this->relationLoaded('activites')) {
            return (float) $this->activites()
                ->get()
                ->sum(function ($activite) {
                    return $activite->getTotalCp();
                });
        }

        // Si les activités sont déjà chargées, utiliser la collection
        return (float) $this->activites->sum(function ($activite) {
            return $activite->getTotalCp();
        });
    }

    /**
     * Accesseur pour total_ae
     * 
     * Permet d'utiliser $action->total_ae dans les vues et Filament
     * 
     * @return float
     */
    public function getTotalAeAttribute(): float
    {
        return $this->getTotalAe();
    }

    /**
     * Accesseur pour total_cp
     * 
     * Permet d'utiliser $action->total_cp dans les vues et Filament
     * 
     * @return float
     */
    public function getTotalCpAttribute(): float
    {
        return $this->getTotalCp();
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
