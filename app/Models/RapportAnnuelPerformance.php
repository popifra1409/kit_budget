<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasWorkflow;

class RapportAnnuelPerformance extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'rapports_annuels_performance';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'exercice_id',
        'ppa_exercice_id',
        'note_explicative',
        'contexte_mise_oeuvre',
        'difficultes_solutions',
        'bilan_strategique_perspectives',
        'lecons_apprises',
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

        return sprintf('RAP-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function ppaExercice(): BelongsTo
    {
        return $this->belongsTo(PpaExercice::class);
    }

    /**
     * Etat de mise en oeuvre par sous-programme (Annexe 10, Chapitre 2) :
     * reutilise l'execution budgetaire reelle deja construite pour le PPA,
     * plus les indicateurs realises vs cibles.
     */
    public function getEtatMiseEnOeuvre(): \Illuminate\Support\Collection
    {
        $annee = (string) $this->exercice?->annee;

        return $this->planStrategiqueEp->sousProgrammes()
            ->with(['programmeBudgetaire'])
            ->get()
            ->map(function ($sp) use ($annee) {
                $activites = \App\Models\Activite::withoutGlobalScope('exercice')
                    ->whereIn('action_id', $sp->actionsPourExercice($this->exercice_id)->pluck('id'))
                    ->with('indicateurs.valeurs')
                    ->get();

                $cpPrevu = $activites->sum(fn($a) => (float) $a->getTotalCp());
                $engage  = $activites->sum(fn($a) => (float) ($a->getExecutionBudgetaire()['engage'] ?? 0));

                $indicateurs = $activites->flatMap->indicateurs->map(function ($ind) use ($annee) {
                    // Derniere valeur saisie sur l'annee evaluee (periodes '2026', '2026-01', '2026-T4'...)
                    $valeur = $ind->valeurs
                        ->filter(fn($v) => str_starts_with((string) $v->periode, $annee))
                        ->sortByDesc('periode')
                        ->first();

                    $cible   = is_numeric($ind->valeur_cible) ? (float) $ind->valeur_cible : null;
                    $realise = is_numeric($valeur?->valeur_realisee) ? (float) $valeur->valeur_realisee : null;

                    return [
                        'libelle'   => $ind->libelle,
                        'reference' => $ind->valeur_reference,
                        'cible'     => $ind->valeur_cible,
                        'realise'   => $valeur?->valeur_realisee,
                        'periode'   => $valeur?->periode,
                        'taux'      => ($cible && $realise !== null) ? round(($realise / $cible) * 100, 1) : null,
                    ];
                });

                return [
                    'sous_programme'  => $sp,
                    'nb_activites'    => $activites->count(),
                    'cp_prevu'        => $cpPrevu,
                    'engage'          => $engage,
                    'disponible'      => $cpPrevu - $engage,
                    'taux_execution'  => $cpPrevu > 0 ? round(($engage / $cpPrevu) * 100, 1) : 0,
                    'indicateurs'     => $indicateurs,
                ];
            });
    }

    /** Totaux consolides pour la ligne "TOTAL" du RAP. */
    public function getTotauxExecution(?\Illuminate\Support\Collection $etat = null): array
    {
        $etat ??= $this->getEtatMiseEnOeuvre();
        $prevu  = $etat->sum('cp_prevu');
        $engage = $etat->sum('engage');

        return [
            'cp_prevu'       => $prevu,
            'engage'         => $engage,
            'disponible'     => $prevu - $engage,
            'taux_execution' => $prevu > 0 ? round(($engage / $prevu) * 100, 1) : 0,
        ];
    }
}
