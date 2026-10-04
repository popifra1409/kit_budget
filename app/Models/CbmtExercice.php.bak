<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class CbmtExercice extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'cbmt_exercices';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'exercice_reference_id',
        'date_lettre_cadrage',
        'hypotheses_ressources',
        'commentaire_soutenabilite',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'date_lettre_cadrage' => 'date',
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

        return sprintf('CBMT-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    public function exerciceReference(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, 'exercice_reference_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CbmtLigne::class);
    }

    public function lignesRessources(): HasMany
    {
        return $this->lignes()->where('nature', 'ressource');
    }

    public function lignesDepenses(): HasMany
    {
        return $this->lignes()->where('nature', 'depense');
    }

    /**
     * Test de soutenabilite : ecart entre ressources et depenses projetees,
     * pour chaque annee du cadrage (I.3 du guide).
     */
    public function getTestSoutenabilite(): array
    {
        $colonnes = ['montant_n_plus_1', 'montant_n_plus_2', 'montant_n_plus_3'];
        $resultat = [];

        foreach ($colonnes as $col) {
            $ressources = $this->lignesRessources()->sum($col);
            $depenses = $this->lignesDepenses()->sum($col);
            $resultat[$col] = [
                'ressources' => $ressources,
                'depenses' => $depenses,
                'ecart' => $ressources - $depenses,
            ];
        }

        return $resultat;
    }
}
