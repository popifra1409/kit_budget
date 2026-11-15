<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        // B2b — le lien qui remplace le rapprochement par code. Déclaré ici dès maintenant :
        //       omis, `Action::create([... 'sous_programme_ep_id' => …])` l'aurait filtré en
        //       silence et l'action serait apparue nulle part.
        'sous_programme_ep_id',
        'code',
        'libelle',
        'description',
        'ordre',
        'actif',
        'responsable_personnel_id',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    protected static function booted(): void
    {
        // ✅ Code généré automatiquement à la création (A1, A2…)
        static::creating(function (self $action) {
            if (blank($action->code)) {
                $action->code = static::genererCode($action->exercice_id);
            }
        });

        static::saving(function (self $action) {
            // B2b — les deux colonnes disent chacune une chose différente, et il ne peut pas y
            // avoir deux réponses à « sous quel sous-programme de l'hôpital rangera-t-on cette
            // action ? ». `programme_id` reste la classification budgétaire (celle du MINSANTE) ;
            // `sous_programme_ep_id` est la décomposition par l'hôpital, qui ne peut passer que
            // par le sous-programme rattaché À CE PROGRAMME.
            //
            // Refuser, pas corriger : un `saving` qui réécrit l'autre colonne fait croire à
            // l'écran que la saisie a été enregistrée, alors qu'elle a été changée. Ici la fiche
            // reçoit le motif et la valeur choisie par l'utilisateur reste intacte.
            if ($action->sous_programme_ep_id === null) {
                return;
            }

            $sp = SousProgrammeEp::withTrashed()->find($action->sous_programme_ep_id);

            if ($sp === null) {
                throw new \InvalidArgumentException(
                    "Le sous-programme de l'EP #$action->sous_programme_ep_id n'existe pas."
                );
            }

            // Les codes sont lus en SQL direct, jamais par `programmeBudgetaire` : la BelongsTo
            // subit le scope d'exercice de la session, et un refus qui nomme un programme vide
            // est un refus illisible.
            if ($sp->programme_budgetaire_id === null) {
                throw new \InvalidArgumentException(
                    "« {$sp->code} » n'a aucun programme de rattachement : il ne peut porter aucune action. "
                        . "Rattachez-le d'abord à un programme budgétaire du MINSANTE."
                );
            }

            $codeSp = (string) Programme::withTrashed()->whereKey($sp->programme_budgetaire_id)->value('code');

            // Pas de branche « programme_id est nul » : la colonne est NOT NULL en base, et ce
            // n'est pas le garde qui pourrait le savoir mieux que PostgreSQL.
            if ((int) $sp->programme_budgetaire_id !== (int) $action->programme_id) {
                $codeAction = (string) Programme::withTrashed()->whereKey($action->programme_id)->value('code');

                throw new \InvalidArgumentException(
                    "« {$sp->code} » décompose le programme « $codeSp », pas le programme « $codeAction » "
                        . "de cette action. Une action se range sous le sous-programme de l'EP qui décompose son propre programme."
                );
            }
        });
    }

    /**
     * ✅ Code automatique : « A » suivi du numéro suivant parmi les éléments de l'exercice
     *    (suppressions comprises : un numéro n'est jamais réutilisé). Les codes existants ne changent pas.
     */
    public static function genererCode(?int $exerciceId): string
    {
        $max = static::withoutGlobalScope('exercice')->withTrashed()
            ->when($exerciceId, fn($q) => $q->where('exercice_id', $exerciceId))
            ->pluck('code')
            ->map(fn($c) => preg_match('/^A(\d+)$/i', trim((string) $c), $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'A' . ($max + 1);
    }

    /**
     * Relation : Programme parent
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    /**
     * Le sous-programme de l'EP (hôpital) qui décompose cette action — B2b.
     *
     * `withTrashed()` : un sous-programme clôturé ou retiré reste la réponse à « qui portait
     * cette action ? », et l'impression d'un PPA d'exercice clos ne doit pas devenir muette.
     */
    public function sousProgrammeEp(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_ep_id')->withTrashed();
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
     * L'agent qui met l'action en œuvre — `personnels`, pas `users` : le cadre logique
     * étendu imprime ce nom sous « Responsable de l'action », et un agent sans compte doit
     * pouvoir être désigné. `withTrashed()` pour qu'un retrait ne fasse pas disparaître un
     * nom déjà diffusé ; la contrainte, elle, ne mord que sur une purge réelle.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_personnel_id')->withTrashed();
    }

    /** Indicateurs de l'action (matrice du cadre logique par sous-programme). */
    public function indicateurs(): MorphMany
    {
        return $this->morphMany(Indicateur::class, 'indicateurable');
    }

    /** Objectif spécifique affiché dans le cadre logique (le premier par ordre). */
    public function objectifPourCadreLogique(): ?string
    {
        return $this->objectifsSpecifiques->sortBy('ordre')->first()?->libelle;
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
