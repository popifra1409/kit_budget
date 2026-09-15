<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

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
        'statut',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
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

    public function actions(): HasMany
    {
        return $this->hasMany(ActionSousProgramme::class, 'sous_programme_ep_id');
    }

    public function indicateurs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Indicateur::class, 'indicateurable');
    }

    public function programmeBudgetaire(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_budgetaire_id');
    }
}
