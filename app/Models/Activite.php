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
use App\Traits\HasCorbeille;

class Activite extends Model
{

    /** Relations dont les lignes partent avec ce document dans la corbeille. */
    public static function corbeilleEnfants(): array
    {
        return ['extrants', 'taches'];
    }

    use HasFactory, SoftDeletes, HasExercice, LogsActivity, HasWorkflow, HasCorbeille;

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
        'responsable_personnel_id',
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

            // ✅ Code généré automatiquement à la création (ACT1, ACT2…)
            if (blank($model->code)) {
                $model->code = static::genererCode($model->exercice_id);
            }
        });
    }

    /**
     * ✅ Code automatique : « ACT » suivi du numéro suivant parmi les éléments de l'exercice
     *    (suppressions comprises : un numéro n'est jamais réutilisé). Les codes existants ne changent pas.
     */
    public static function genererCode(?int $exerciceId): string
    {
        $max = static::withoutGlobalScope('exercice')->withTrashed()
            ->when($exerciceId, fn($q) => $q->where('exercice_id', $exerciceId))
            ->pluck('code')
            ->map(fn($c) => preg_match('/^ACT(\d+)$/i', trim((string) $c), $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'ACT' . ($max + 1);
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

    /** L'agent qui met l'activité en œuvre — voir Action::responsable() pour le pourquoi. */
    public function responsable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_personnel_id')->withTrashed();
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
            ->porteusesBudget()   // ✅ sous-tâches + tâches liées directement à une nomenclature
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

    /** Scope : ce qu'un nouveau document a le droit de choisir (CDMT, budgets, états de saisie). */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Motifs qui EMPÊCHENT de désactiver l'activité — liste vide = désactivable.
     *
     * AVOIR des tâches ne bloque pas : ce sont les tâches UTILISÉES qui bloquent. Sans cela,
     * aucune activité ne serait jamais désactivable (30 des 31 activités portent des tâches) et la
     * cascade demandée n'aurait jamais lieu d'être.
     *
     * @return string[] motifs libellés, prêts à mettre dans le refus affiché
     */
    public function motifsDesactivation(): array
    {
        if (!$this->exists) {
            return [];
        }

        $motifs = [];

        foreach ($this->tachesDeLActivite() as $tache) {
            $motifsTache = $tache->motifsDesactivation();
            if ($motifsTache !== []) {
                $motifs[] = "sa tâche {$tache->code} : " . implode(' ; ', $motifsTache);
            }
        }

        foreach ([
            ['cdmt_lignes', 'activite_id', 'ligne(s) de CDMT la citent'],
            ['extrants', 'activite_id', 'extrant(s) rattaché(s)'],
            ['rapports_activites_periodiques', 'activite_id', 'rapport(s) d\'activité périodique(s) la citent'],
        ] as [$table, $colonne, $libelle]) {
            $n = (int) \Illuminate\Support\Facades\DB::table($table)->where($colonne, $this->id)->count();
            if ($n > 0) {
                $motifs[] = "$n $libelle";
            }
        }

        return $motifs;
    }

    /** Se désactive sans rien casser ? */
    public function estDesactivable(): bool
    {
        return $this->motifsDesactivation() === [];
    }

    /**
     * Ses tâches, hors scope exercice : une tâche d'un autre exercice rattachée à cette activité
     * doit être vue et entraînée aussi, sinon l'activité serait inactive avec une branche active.
     *
     * `unique('id')` n'est pas décoratif : une sous-tâche porte déjà `activite_id`, donc la seconde
     * requête (les enfants des tâches trouvées) la ramène une deuxième fois — elle bloquerait deux
     * fois et se désactiverait deux fois.
     */
    public function tachesDeLActivite(): \Illuminate\Support\Collection
    {
        $taches = Tache::withoutGlobalScope('exercice')->where('activite_id', $this->id)->get();

        return $taches
            ->concat(
                Tache::withoutGlobalScope('exercice')
                    ->whereIn('parent_id', $taches->pluck('id')->all())->get()
            )
            ->unique('id')
            ->values();
    }

    /** Sur quoi la désactivation de l'activité se propage. */
    public function tachesADesactiver(): \Illuminate\Support\Collection
    {
        return $this->tachesDeLActivite();
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
