<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class RapportActivitePeriodique extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'rapports_activites_periodiques';

    protected $fillable = [
        'numero',
        'activite_id',
        'periode',
        'type_periode',
        'poids_activite',
        'problemes_rencontres',
        'solutions_proposees',
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
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('RAP-ACT-%d-%05d', $annee, $dernier + 1);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(RapportActiviteLigne::class);
    }

    public function lignesTaches(): HasMany
    {
        return $this->lignes()->where('nature', 'tache');
    }

    public function lignesMoyens(): HasMany
    {
        return $this->lignes()->where('nature', 'moyen');
    }

    /**
     * Pre-remplit les lignes "tache" a partir des vraies Taches de
     * l'Activite (AE = prevision), pour eviter la ressaisie.
     */
    public function genererLignesDepuisTaches(): void
    {
        foreach ($this->activite->taches()->where('niveau', 'sous_tache')->get() as $tache) {
            $this->lignes()->firstOrCreate(
                ['tache_id' => $tache->id, 'nature' => 'tache'],
                ['libelle' => $tache->libelle, 'unite' => 'FCFA', 'prevision' => $tache->ae, 'realisation' => 0]
            );
        }
    }

    /** Totaux Prevision / Realisation / Ecart / Taux pour 'tache' ou 'moyen'. */
    public function getSynthese(string $nature): array
    {
        $lignes = $this->lignes->where('nature', $nature);
        $prev = $lignes->sum(fn($l) => (float) $l->prevision);
        $real = $lignes->sum(fn($l) => (float) $l->realisation);

        return [
            'prevision'   => $prev,
            'realisation' => $real,
            'ecart'       => $real - $prev,
            'taux'        => $prev > 0 ? round(($real / $prev) * 100, 1) : null,
        ];
    }

    /**
     * Remplit la colonne Realisation des lignes "tache" a partir de l'engage
     * reel du module Budget.
     *
     * @param string $mode 'cumul' (1er janvier -> fin de periode) ou 'periode' (periode seule)
     * @param bool $ecraserManuelles remplacer aussi les realisations saisies a la main
     */
    public function actualiserRealisationsDepuisBudget(string $mode = 'cumul', bool $ecraserManuelles = false): array
    {
        if (!$this->estModifiable()) {
            throw new \DomainException('Ce rapport est transmis ou validé : ses réalisations sont figées.');
        }

        $service = app(\App\Services\SuiviEvaluation\ExecutionBudgetaireService::class);
        [$debut, $fin] = $service->bornesPeriode($this->periode, $this->type_periode);

        if ($mode === 'cumul') {
            $debut = $debut->copy()->startOfYear();
        }

        $exerciceId = $this->activite->exercice_id;
        $stats = ['mises_a_jour' => 0, 'manuelles_conservees' => 0, 'sans_ligne_budgetaire' => 0, 'source' => null];

        \Illuminate\Support\Facades\DB::transaction(function () use ($service, $debut, $fin, $exerciceId, $ecraserManuelles, &$stats) {
            foreach ($this->lignesTaches()->with('tache')->get() as $ligne) {
                if ($ligne->source_realisation === 'manuelle' && !$ecraserManuelles) {
                    $stats['manuelles_conservees']++;
                    continue;
                }

                $ligneBudgetaire = $ligne->tache?->ligneBudgetaire();
                if (!$ligneBudgetaire) {
                    $stats['sans_ligne_budgetaire']++;
                    continue;
                }

                $engage = $service->montantEngage($ligneBudgetaire, $debut, $fin);
                $part   = $service->quotePart($ligne->tache, $exerciceId);

                $ligne->update([
                    'realisation'               => round($engage['montant'] * $part, 2),
                    'source_realisation'        => 'budget',
                    'ligne_budgetaire_id'       => $ligneBudgetaire->id,
                    'quote_part'                => $part,
                    'realisation_actualisee_le' => now(),
                ]);

                $stats['source'] = $engage['source'];
                $stats['mises_a_jour']++;
            }
        });

        return $stats + ['debut' => $debut->format('d/m/Y'), 'fin' => $fin->format('d/m/Y')];
    }
}
