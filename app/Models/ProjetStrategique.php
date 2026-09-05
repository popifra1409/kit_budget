<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasWorkflow;

class ProjetStrategique extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'projets_strategiques';

    protected $fillable = [
        'numero',
        'action_sous_programme_id',
        'activite_budgetaire_id',
        'code',
        'libelle',
        'description',
        'date_debut_prevue',
        'date_fin_prevue',
        'responsable_id',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'date_debut_prevue' => 'date',
        'date_fin_prevue' => 'date',
    ];

    protected static function boot(): void
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
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('PST-%d-%05d', $annee, $dernier + 1);
    }

    public function actionSousProgramme(): BelongsTo
    {
        return $this->belongsTo(ActionSousProgramme::class, 'action_sous_programme_id');
    }

    /**
     * Lien optionnel vers l'objet de classification budgetaire
     * (module Budget — imputation/nomenclature). Purement informatif,
     * n'entraine aucune dependance obligatoire.
     */
    public function activiteBudgetaire(): BelongsTo
    {
        return $this->belongsTo(Activite::class, 'activite_budgetaire_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
