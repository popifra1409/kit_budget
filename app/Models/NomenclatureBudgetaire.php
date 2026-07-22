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
     * Relation : Groupe de nomenclature (ex: DÉPENSES DE FONCTIONNEMENT, RECETTES PROPRES...)
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(GroupeNomenclature::class, 'groupe_id');
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
     * Scope : Par groupe de nomenclature
     */
    public function scopeGroupe($query, $groupeId)
    {
        return $query->where('groupe_id', $groupeId);
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

    public function lignePrevisionRecettes()
    {
        return $this->hasMany(LignePrevisionRecette::class, 'nomenclature_id');
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

            // Valider la cohérence type <-> groupe
            if ($nomenclature->groupe_id) {
                $groupe = GroupeNomenclature::find($nomenclature->groupe_id);

                if ($groupe && $groupe->type !== $nomenclature->type) {
                    throw new \Exception(
                        "Le type de la ligne ('{$nomenclature->type}') ne correspond pas au type du groupe "
                            . "'{$groupe->libelle}' ('{$groupe->type}')."
                    );
                }
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

    /**
     * Obtenir le code de l'article (nomenclature de niveau 'article')
     * Remonte la hiérarchie si nécessaire
     */
    public function getCodeArticle(): string
    {
        // Si c'est déjà un article, retourner son code
        if ($this->niveau === 'article') {
            return $this->code;
        }

        // Si c'est un paragraphe, l'article est le parent direct
        if ($this->niveau === 'paragraphe' && $this->parent) {
            if ($this->parent->niveau === 'article') {
                return $this->parent->code;
            }
        }

        // Si c'est une ligne, remonter de 2 niveaux
        if ($this->niveau === 'ligne' && $this->parent) {
            // Parent = paragraphe
            if ($this->parent->parent) {
                // Grand-parent = article
                if ($this->parent->parent->niveau === 'article') {
                    return $this->parent->parent->code;
                }
            }
        }

        // Si on n'a pas trouvé d'article, fallback sur les 3 premiers caractères
        return substr($this->code, 0, 3);
    }

    /**
     * Vérifie que le type de la ligne correspond au type de son groupe (si rattachée).
     * Utile pour un contrôle en amont (FormRequest) sans déclencher d'exception.
     */
    public function typeCoherentAvecGroupe(): bool
    {
        if (!$this->groupe_id) {
            return true; // pas de groupe = pas de contrainte
        }

        return $this->groupe?->type === $this->type;
    }
}
