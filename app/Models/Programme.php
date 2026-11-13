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
        'responsable_personnel_id',
        'annee',
        'actif',
    ];

    protected $casts = [
        'niveau' => 'string',
        'annee' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Étape 4 — un seul propriétaire du sous-programme.
     *
     * `niveau='sous_programme'` et `parent_id` ne s'écrivent plus depuis ici : le
     * sous-programme de gestion interne est une ligne de `sous_programmes_ep`, reliée à ce
     * programme par `programme_budgetaire_id`. Les deux colonnes restent LUES — six gabarits
     * d'ordonnance et de certificat, `LigneBudgetaire::classification()`,
     * `NomenclatureBudgetaire`, `DisponibilitesBudgetExport`, la reconduction — et ne
     * rencontrent plus que des lignes à « programme », donc des branches éteintes, pas des
     * erreurs.
     *
     * `isDirty()` et non la valeur seule : une ligne héritée qui porte déjà `sous_programme`
     * doit rester consultable et modifiable telle quelle. Sinon la reconduction d'un exercice
     * passerait son temps à refuser le passé plutôt qu'une nouveauté.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model) {
            if ($model->isDirty('niveau') && $model->niveau !== 'programme') {
                throw new \InvalidArgumentException(
                    "Un programme est toujours de niveau « programme ». Le sous-programme de gestion interne "
                        . "n'est plus une ligne de cette table : créez-le dans Planification → Sous-programmes, "
                        . "où son champ « Programme de rattachement (ministériel) » pointera « {$model->code} »."
                );
            }

            if ($model->isDirty('parent_id') && filled($model->parent_id)) {
                throw new \InvalidArgumentException(
                    "Le « programme parent » n'a plus de sens : plus aucun sous-programme ne vit dans cette table. "
                        . "Rattachez-le depuis Planification → Sous-programmes."
                );
            }
        });
    }

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
     * Relation : l'agent responsable du programme ou du sous-programme.
     *
     * `withTrashed()` : Personnel est en SoftDeletes, donc la contrainte `nullOnDelete`
     * de la colonne ne se déclenche jamais sur un `delete()` ordinaire — l'identifiant
     * reste posé. Sans ce scope, la BelongsTo masquerait la ligne trashed et renverrait
     * null : le cadre logique perdrait son responsable sans rien signaler.
     */
    public function responsablePersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_personnel_id')->withTrashed();
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
     * Relation : les sous-programmes de l'EP rattachés à ce programme (Étape 4).
     *
     * C'est la lecture qui remplace `sousProgrammes()` pour tout ce qui est nouveau : le lien
     * vit dans `sous_programmes_ep.programme_budgetaire_id`, contrôlé par
     * `SousProgrammeEp::boot()` côté enfant — seul un programme de niveau « programme » peut
     * être désigné comme rattachement.
     */
    public function sousProgrammesEp(): HasMany
    {
        return $this->hasMany(SousProgrammeEp::class, 'programme_budgetaire_id');
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
