<?php

namespace App\Models;

use App\Traits\HasWorkflow;

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
    use HasFactory, SoftDeletes, HasExercice, LogsActivity, HasWorkflow;

    protected $table = 'activites';

    protected $fillable = [
        'exercice_id',
        'action_id',
        'code',
        'libelle',
        'description',
        'ordre',
        'actif',
        'numero',
        'statut',
        'created_by',
        'objectif',
        'zone_execution',
        'responsable_id',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->whereNotNull('numero')->count();

        return sprintf('ACT-%d-%05d', $annee, $dernier + 1);
    }

    /**
     * Relation : Action parent
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }

    /**
     * Relation : Tâches de l'activité
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }

    public function indicateurs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Indicateur::class, 'indicateurable');
    }

    public function extrants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Extrant::class);
    }

    public function responsable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
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
     * Execution budgetaire REELLE de cette activite (module Budget), agregee
     * a partir des LigneBudgetaire de ses sous-taches. Distinct de
     * getTotalAe()/getTotalCp() qui restent le PREVISIONNEL de planification.
     */
    public function getExecutionBudgetaire(): array
    {
        $lignes = $this->taches()
            ->where('niveau', 'sous_tache')
            ->get()
            ->map(fn($t) => $t->ligneBudgetaire())
            ->filter();

        $budgetRectifie = $lignes->sum('budget_rectifie');
        $engage = $lignes->sum('engage');
        $disponible = $lignes->sum('disponible_engagement');

        return [
            'budget_rectifie' => $budgetRectifie,
            'engage' => $engage,
            'disponible' => $disponible,
            'taux_engagement' => $budgetRectifie > 0 ? round(($engage / $budgetRectifie) * 100, 1) : 0,
            'nb_lignes' => $lignes->count(),
        ];
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
     * Calculer le total AE de toutes les tâches principales
     * (Utilise getBudgetTotal() existant)
     */
    public function getTotalAe(): float
    {
        // Si les tâches sont déjà chargées, utiliser la collection
        if ($this->relationLoaded('taches')) {
            return (float) $this->taches
                ->where('niveau', 'tache')
                ->sum(function ($tache) {
                    return $tache->getTotalAe();
                });
        }

        // Sinon, utiliser la méthode existante qui fait une requête
        return $this->getBudgetTotal();
    }

    /**
     * Calculer le total CP de toutes les tâches principales
     * (Utilise getBudgetCpTotal() existant)
     */
    public function getTotalCp(): float
    {
        // Si les tâches sont déjà chargées, utiliser la collection
        if ($this->relationLoaded('taches')) {
            return (float) $this->taches
                ->where('niveau', 'tache')
                ->sum(function ($tache) {
                    return $tache->getTotalCp();
                });
        }

        // Sinon, utiliser la méthode existante qui fait une requête
        return $this->getBudgetCpTotal();
    }

    /**
     * Accesseur pour total_ae
     */
    public function getTotalAeAttribute(): float
    {
        return $this->getTotalAe();
    }

    /**
     * Accesseur pour total_cp
     */
    public function getTotalCpAttribute(): float
    {
        return $this->getTotalCp();
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
