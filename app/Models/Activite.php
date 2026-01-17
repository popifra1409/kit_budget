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

class Activite extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'activites';

    protected $fillable = [
        'exercice_id',
        'action_id',
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
     * Relation : Action parent
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /**
     * Relation : Tâches de l'activité
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }

    /**
     * Obtenir le budget total (AE) de l'activité
     * = Somme des AE de toutes les tâches (qui incluent leurs sous-tâches)
     */
    public function getBudgetTotal(): float
    {
        return $this->taches()
            ->where('niveau', 'tache') // Uniquement les tâches principales
            ->get()
            ->sum(function ($tache) {
                return $tache->getTotalAe();
            });
    }

    /**
     * Obtenir le budget CP total de l'activité
     */
    public function getBudgetCpTotal(): float
    {
        return $this->taches()
            ->where('niveau', 'tache') // Uniquement les tâches principales
            ->get()
            ->sum(function ($tache) {
                return $tache->getTotalCp();
            });
    }

    /**
     * Obtenir le nombre de tâches (principales uniquement)
     */
    public function getNombreTaches(): int
    {
        return $this->taches()->where('niveau', 'tache')->count();
    }

    /**
     * Obtenir le nombre total de sous-tâches
     */
    public function getNombreSousTaches(): int
    {
        return $this->taches()->where('niveau', 'sous_tache')->count();
    }

    /**
     * Vérifier si modifiable (selon exercice)
     */
    public function estModifiable(): bool
    {
        return $this->exercice && $this->exercice->estModifiable();
    }

    /**
     * Vérifier si en lecture seule
     */
    public function estLectureSeule(): bool
    {
        return $this->exercice && !$this->exercice->estModifiable();
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
