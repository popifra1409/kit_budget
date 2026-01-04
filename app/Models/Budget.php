<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Budget extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $fillable = [
        'exercice_id',
        'code',
        'libelle',
        'exercice',
        'date_adoption',
        'observations',
        'statut',
        'actif',
    ];

    protected $casts = [
        'date_adoption' => 'date',
        'exercice' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Lignes budgétaires
     */
    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class);
    }

    /**
     * Relation : Virements budgétaires
     */
    public function virements(): HasMany
    {
        return $this->hasMany(VirementBudgetaire::class);
    }

    /**
     * Scope : Par exercice
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : Actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Obtenir le budget total initial
     */
    public function getBudgetTotalInitial(): float
    {
        return $this->lignesBudgetaires()->sum('budget_initial');
    }

    /**
     * Obtenir le budget total rectifié
     */
    public function getBudgetTotalRectifie(): float
    {
        return $this->lignesBudgetaires()->sum('budget_rectifie');
    }

    /**
     * Obtenir le total engagé
     */
    public function getTotalEngage(): float
    {
        return $this->lignesBudgetaires()->sum('engage');
    }

    /**
     * Obtenir le total ordonné
     */
    public function getTotalOrdonne(): float
    {
        return $this->lignesBudgetaires()->sum('ordonne');
    }

    /**
     * Obtenir le total liquidé
     */
    public function getTotalLiquide(): float
    {
        return $this->lignesBudgetaires()->sum('liquide');
    }

    /**
     * Obtenir le total payé
     */
    public function getTotalPaye(): float
    {
        return $this->lignesBudgetaires()->sum('paye');
    }

    /**
     * Obtenir le disponible total
     */
    public function getDisponibleTotal(): float
    {
        return $this->lignesBudgetaires()->sum('disponible_engagement');
    }

    /**
     * Taux d'exécution (liquidé / budget rectifié)
     */
    public function getTauxExecution(): float
    {
        $budgetRectifie = $this->getBudgetTotalRectifie();
        if ($budgetRectifie == 0) {
            return 0;
        }
        return ($this->getTotalLiquide() / $budgetRectifie) * 100;
    }

    /**
     * Taux d'engagement (engagé / budget rectifié)
     */
    public function getTauxEngagement(): float
    {
        $budgetRectifie = $this->getBudgetTotalRectifie();
        if ($budgetRectifie == 0) {
            return 0;
        }
        return ($this->getTotalEngage() / $budgetRectifie) * 100;
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
