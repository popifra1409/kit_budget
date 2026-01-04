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

class Tache extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'taches';

    protected $fillable = [
        'exercice_id',
        'activite_id',
        'parent_id',
        'niveau',
        'nomenclature_id',
        'code',
        'libelle',
        'description',
        'delai',
        'guichet',
        'service_id',
        'ae',
        'cp',
        'resultat_attendu',
        'indicateur_resultat',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'ae' => 'decimal:2',
        'cp' => 'decimal:2',
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Activité parent
     */
    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    /**
     * Relation : Tâche parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'parent_id');
    }

    /**
     * Relation : Sous-tâches
     */
    public function sousTaches(): HasMany
    {
        return $this->hasMany(Tache::class, 'parent_id')->where('niveau', 'sous_tache');
    }

    /**
     * Relation : Enfants (tâches ou sous-tâches)
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Tache::class, 'parent_id');
    }

    /**
     * Relation : Nomenclature budgétaire liée
     * NOTE: Uniquement pour les sous-tâches
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Relation : Service responsable
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Scope : Tâches principales (sans parent)
     */
    public function scopeTachesPrincipales($query)
    {
        return $query->whereNull('parent_id')->where('niveau', 'tache');
    }

    /**
     * Scope : Sous-tâches uniquement
     */
    public function scopeSousTaches($query)
    {
        return $query->where('niveau', 'sous_tache');
    }

    /**
     * Vérifier si c'est une tâche principale
     */
    public function estTachePrincipale(): bool
    {
        return $this->niveau === 'tache' && is_null($this->parent_id);
    }

    /**
     * Vérifier si c'est une sous-tâche
     */
    public function estSousTache(): bool
    {
        return $this->niveau === 'sous_tache';
    }

    /**
     * Obtenir le chemin hiérarchique
     */
    public function getCheminHierarchique(): string
    {
        if ($this->estTachePrincipale()) {
            return $this->code . ' - ' . $this->libelle;
        }

        return ($this->parent ? $this->parent->code . ' > ' : '') . $this->code . ' - ' . $this->libelle;
    }

    /**
     * Obtenir le chemin complet du cadre logique
     */
    public function getCheminComplet(): array
    {
        $activite = $this->activite;
        $action = $activite->action;
        $programme = $action->programme;

        return [
            'programme' => $programme->code . ' - ' . $programme->libelle,
            'objectif_principal' => $programme->objectifsPrincipaux->first()?->libelle ?? '',
            'action' => $action->code . ' - ' . $action->libelle,
            'objectif_specifique' => $action->objectifsSpecifiques->first()?->libelle ?? '',
            'activite' => $activite->code . ' - ' . $activite->libelle,
            'tache' => $this->getCheminHierarchique(),
        ];
    }

    /**
     * Obtenir le programme
     */
    public function getProgramme(): Programme
    {
        return $this->activite->action->programme;
    }

    /**
     * Obtenir l'action
     */
    public function getAction(): Action
    {
        return $this->activite->action;
    }

    /**
     * Obtenir la nomenclature (même si c'est une tâche parent)
     * Retourne la nomenclature de la première sous-tâche si c'est une tâche parent
     */
    public function getNomenclatureEffective(): ?NomenclatureBudgetaire
    {
        if ($this->estSousTache()) {
            return $this->nomenclature;
        }

        // Si c'est une tâche parent, prendre la nomenclature de la première sous-tâche
        return $this->sousTaches->first()?->nomenclature;
    }

    /**
     * Boot - Calculer automatiquement AE/CP des tâches parentes
     */
    protected static function boot()
    {
        parent::boot();

        // Recalculer après sauvegarde d'une sous-tâche
        static::saved(function ($tache) {
            if ($tache->estSousTache() && $tache->parent) {
                $tache->parent->recalculerBudget();
            }
        });

        // Recalculer après suppression d'une sous-tâche
        static::deleted(function ($tache) {
            if ($tache->estSousTache() && $tache->parent) {
                $tache->parent->recalculerBudget();
            }
        });
    }

    /**
     * Recalculer les montants AE et CP (pour tâches principales)
     */
    public function recalculerBudget(): void
    {
        if ($this->estTachePrincipale()) {
            $this->ae = $this->sousTaches()->sum('ae');
            $this->cp = $this->sousTaches()->sum('cp');

            // Sauvegarder sans déclencher les événements (éviter boucle infinie)
            $this->saveQuietly();
        }
    }

    /**
     * Obtenir le total AE (calculé ou saisi)
     */
    public function getTotalAe(): float
    {
        if ($this->estTachePrincipale()) {
            return $this->sousTaches()->sum('ae');
        }

        return (float) $this->ae;
    }

    /**
     * Obtenir le total CP (calculé ou saisi)
     */
    public function getTotalCp(): float
    {
        if ($this->estTachePrincipale()) {
            return $this->sousTaches()->sum('cp');
        }

        return (float) $this->cp;
    }

    /**
     * Accessor : AE affiché
     */
    public function getAeFormatteAttribute(): string
    {
        return number_format($this->getTotalAe(), 0, ',', ' ') . ' FCFA';
    }

    /**
     * Accessor : CP affiché
     */
    public function getCpFormatteAttribute(): string
    {
        return number_format($this->getTotalCp(), 0, ',', ' ') . ' FCFA';
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
