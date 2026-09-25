<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SousProgrammeEp extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'sous_programmes_ep';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'code',
        'libelle',
        'description',
        'responsable_id',
        'code_programme_ep',
        'type',
        'statut',
        'created_by',
        'objectif',
        'strategie',
        'cadre_institutionnel',
        'responsable_id'
    ];

    public const MAX_SOUS_PROGRAMMES = 4;
    public const MAX_SUPPORT = 1;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();

            static::validerLimiteSousProgrammes($model);
        });

        static::saving(function (self $model) {
            if ($model->programme_budgetaire_id) {
                $programme = Programme::find($model->programme_budgetaire_id);
                if ($programme && $programme->niveau !== 'programme') {
                    throw new \InvalidArgumentException(
                        "Un Sous-Programme stratégique ne peut être lié qu'à un Programme budgétaire "
                            . "de niveau 'programme' (codes type 413, 414...), pas à un niveau 'sous_programme' "
                            . "(qui est une subdivision de gestion interne sans rapport avec le plan stratégique)."
                    );
                }
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('type')) {
                static::validerLimiteSousProgrammes($model);
            }
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('SPE-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Actions de la classification budgetaire rattachees au Programme
     * budgetaire lie a ce sous-programme strategique.
     * (SousProgrammeEp -> programme_budgetaire_id == Action.programme_id)
     */
    public function actions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Action::class,
            Programme::class,
            'code',               // colonne de Programme comparee au sous-programme
            'programme_id',       // cle d'Action vers Programme
            'code_programme_ep',  // colonne du sous-programme
            'id'                  // cle de Programme
        );
    }

    public function indicateurs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Indicateur::class, 'indicateurable');
    }

    /**
     * Programme MINISTERIEL de rattachement (ex: P-410 Prevention de la maladie).
     * Sans scope d'exercice : le rattachement reste lisible quel que soit l'exercice actif.
     */
    public function programmeBudgetaire(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_budgetaire_id')
            ->withoutGlobalScope('exercice');
    }

    /** Alias explicite, plus lisible dans les nouvelles vues. */
    public function programmeRattachement(): BelongsTo
    {
        return $this->programmeBudgetaire();
    }

    /**
     * Programme budgetaire de l'EP (ex: SP-1) pour un exercice donne.
     */
    public function programmeEp(?int $exerciceId = null): ?Programme
    {
        if (blank($this->code_programme_ep)) {
            return null;
        }

        return Programme::withoutGlobalScope('exercice')
            ->where('code', $this->code_programme_ep)
            ->where('exercice_id', $exerciceId ?? Exercice::getActif()?->id)
            ->first();
    }

    /**
     * Actions du sous-programme pour un exercice donne, resolues par CODE de programme EP.
     * Methode a privilegier dans tous les rapports (PPA, RAP, matrice, libelles).
     */
    public function actionsPourExercice(?int $exerciceId = null): Builder
    {
        $exerciceId ??= Exercice::getActif()?->id;

        $programmeIds = Programme::withoutGlobalScope('exercice')
            ->where('code', $this->code_programme_ep)
            ->where('exercice_id', $exerciceId)
            ->pluck('id');

        return Action::withoutGlobalScope('exercice')
            ->whereIn('programme_id', $programmeIds)
            ->where('exercice_id', $exerciceId)
            ->orderBy('code');
    }

    /**
     * Instruction du 22 janvier 2026 : un EP ne peut avoir plus de 4
     * sous-programmes (3 operationnels + 1 support maximum).
     */
    protected static function validerLimiteSousProgrammes(self $model): void
    {
        $query = static::where('plan_strategique_ep_id', $model->plan_strategique_ep_id)
            ->when($model->exists, fn($q) => $q->where('id', '!=', $model->id));

        $totalExistant = $query->count();

        if ($totalExistant >= self::MAX_SOUS_PROGRAMMES) {
            throw new \Exception(
                "Ce Plan Stratégique compte déjà " . self::MAX_SOUS_PROGRAMMES
                    . " sous-programmes. L'Instruction du 22 janvier 2026 limite ce nombre à "
                    . self::MAX_SOUS_PROGRAMMES . " maximum (3 opérationnels + 1 support)."
            );
        }

        if (($model->type ?? 'operationnel') === 'support') {
            $nbSupport = (clone $query)->where('type', 'support')->count();
            if ($nbSupport >= self::MAX_SUPPORT) {
                throw new \Exception(
                    "Ce Plan Stratégique a déjà un sous-programme de type 'support'. "
                        . "Un seul sous-programme support est autorisé par établissement."
                );
            }
        }
    }
}
