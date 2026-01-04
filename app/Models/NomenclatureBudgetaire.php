<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class NomenclatureBudgetaire extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'nomenclature_budgetaire';

    protected $fillable = [
        'exercice_id',
        'code',
        'libelle',
        'classe',
        'type',
        'niveau',
        'parent_id',
        'date_mise_en_vigueur',
        'exercice',
        'code_precedent',
        'version_precedente_id',
        'version',
        'motif_modification',
        'modifie_par',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'date_mise_en_vigueur' => 'date',
        'actif' => 'boolean',
        'version' => 'integer',
        'ordre' => 'integer',
        'exercice' => 'integer',
    ];

    /**
     * Relation : Parent dans la hiérarchie
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'parent_id');
    }

    /**
     * Relation : Enfants dans la hiérarchie
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(NomenclatureBudgetaire::class, 'parent_id');
    }

    /**
     * Relation : Version précédente
     */
    public function versionPrecedente(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'version_precedente_id');
    }

    /**
     * Relation : Versions ultérieures
     */
    public function versionsUlterieures(): HasMany
    {
        return $this->hasMany(NomenclatureBudgetaire::class, 'version_precedente_id');
    }

    /**
     * Relation : Utilisateur ayant modifié
     */
    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    /**
     * Relation : Tâche principale liée (sous-tâche uniquement)
     */
    public function tache(): HasOne
    {
        return $this->hasOne(Tache::class, 'nomenclature_id')->where('niveau', 'sous_tache');
    }

    /**
     * Relation : Toutes les tâches liées à cette nomenclature
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class, 'nomenclature_id');
    }

    /**
     * Obtenir les informations du cadre logique pour cette nomenclature
     */
    public function getCadreLogique()
    {
        $tache = $this->taches()->with([
            'activite.action.programme.objectifsPrincipaux',
            'activite.action.objectifsSpecifiques'
        ])->first();

        if (!$tache) {
            return null;
        }

        return $tache->getCheminComplet();
    }

    /**
     * Scope : Nomenclatures actives (en cours)
     */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Nomenclatures pour un exercice donné
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    /**
     * Scope : Par type (depense/recette)
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope : Par classe (6/7)
     */
    public function scopeClasse($query, $classe)
    {
        return $query->where('classe', $classe);
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
     * Boot - Valider la hiérarchie
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($nomenclature) {
            // Valider la cohérence parent/niveau
            if ($nomenclature->parent_id) {
                $parent = NomenclatureBudgetaire::find($nomenclature->parent_id);

                if ($nomenclature->niveau === 'article' && $parent->niveau !== 'chapitre') {
                    throw new \Exception("Un Article doit avoir un Chapitre comme parent");
                }

                if ($nomenclature->niveau === 'paragraphe' && !in_array($parent->niveau, ['article', 'chapitre'])) {
                    throw new \Exception("Un Paragraphe doit avoir un Article ou un Chapitre comme parent");
                }
            }

            // Chapitre ne peut pas avoir de parent
            if ($nomenclature->niveau === 'chapitre' && $nomenclature->parent_id) {
                throw new \Exception("Un Chapitre ne peut pas avoir de parent");
            }
        });
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
