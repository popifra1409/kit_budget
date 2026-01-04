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
        return $this->belongsTo(Programme::class);
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
     * Obtenir le budget total de l'action
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'date_bordereau', 'montant_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Bordereau {$eventName}");
    }
}
