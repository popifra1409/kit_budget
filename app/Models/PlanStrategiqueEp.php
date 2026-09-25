<?php
// app/Models/PlanStrategiqueEp.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class PlanStrategiqueEp extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'plans_strategiques_ep';

    protected $fillable = [
        'numero',
        'csp_ministere_id',
        'parametres_structure_id',
        'code',
        'libelle',
        'description',
        'contexte_elaboration',
        'domaines_intervention',
        'objectif_strategique',
        'periode_debut',
        'periode_fin',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
            $model->parametres_structure_id ??= ParametresStructure::getParametres()?->id;
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()
            ->whereYear('created_at', $annee)
            ->count();

        return sprintf('PSE-%d-%05d', $annee, $dernier + 1);
    }

    public function cspMinistere(): BelongsTo
    {
        return $this->belongsTo(CspMinistereSante::class, 'csp_ministere_id');
    }

    public function parametresStructure(): BelongsTo
    {
        return $this->belongsTo(ParametresStructure::class, 'parametres_structure_id');
    }

    public function sousProgrammes(): HasMany
    {
        return $this->hasMany(SousProgrammeEp::class, 'plan_strategique_ep_id');
    }

    public function ppaExercices(): HasMany
    {
        return $this->hasMany(PpaExercice::class, 'plan_strategique_ep_id');
    }

    /**
     * Synthese annee par annee (prevu vs execution reelle), sur tous
     * les PPA deja crees pour ce PSP.
     * Chaque PPA est calcule sur SON exercice (et non sur l'exercice actif).
     */
    public function getSyntheseParExercice(): \Illuminate\Support\Collection
    {
        $sousProgrammes = $this->sousProgrammes()->get();

        return $this->ppaExercices()
            ->with('exercice')
            ->get()
            ->sortBy(fn($ppa) => $ppa->exercice?->annee)
            ->map(function (PpaExercice $ppa) use ($sousProgrammes) {
                $exerciceId = $ppa->exercice_id;

                $totalAe = 0;
                $totalCp = 0;
                $totalEngage = 0;
                $totalDisponible = 0;

                foreach ($sousProgrammes as $sp) {
                    // Actions du programme EP (SP-1...) pour l'exercice du PPA
                    $actionIds = $sp->actionsPourExercice($exerciceId)->pluck('id');

                    $activites = Activite::withoutGlobalScope('exercice')
                        ->whereIn('action_id', $actionIds)
                        ->where('exercice_id', $exerciceId)
                        ->get();

                    foreach ($activites as $activite) {
                        $totalAe += $activite->getTotalAe();
                        $totalCp += $activite->getTotalCp();
                        $exec = $activite->getExecutionBudgetaire();
                        $totalEngage += $exec['engage'];
                        $totalDisponible += $exec['disponible'];
                    }
                }

                return [
                    'ppa_id'         => $ppa->id,
                    'ppa_numero'     => $ppa->numero,
                    'exercice'       => $ppa->exercice?->annee,
                    'ae_prevu'       => $totalAe,
                    'cp_prevu'       => $totalCp,
                    'engage_reel'    => $totalEngage,
                    'disponible'     => $totalDisponible,
                    'taux_execution' => $totalCp > 0 ? round(($totalEngage / $totalCp) * 100, 1) : 0,
                    'statut_ppa'     => $ppa->statut,
                ];
            })
            ->values();
    }

    /**
     * Cumul sur toute la duree du PSP (somme de tous les exercices
     * couverts par un PPA existant).
     */
    public function getCumulPluriannuel(): array
    {
        $synthese = $this->getSyntheseParExercice();

        return [
            'total_ae_prevu' => $synthese->sum('ae_prevu'),
            'total_cp_prevu' => $synthese->sum('cp_prevu'),
            'total_engage' => $synthese->sum('engage_reel'),
            'total_disponible' => $synthese->sum('disponible'),
            'nb_exercices_couverts' => $synthese->count(),
            'nb_exercices_psp' => $this->periode_debut && $this->periode_fin
                ? $this->periode_fin->diffInYears($this->periode_debut) + 1
                : null,
        ];
    }
}
