<?php
// app/Models/PpaExercice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use App\Traits\HasWorkflow;

class PpaExercice extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'ppa_exercices';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'exercice_id',
        'contexte_introduction',
        'performances_anterieures',
        'bilan_technique',
        'bilan_financier',
        'cdmt_exercice_id',
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

        return sprintf('PPA-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, 'exercice_id');
    }

    public function cdmtExercice(): BelongsTo
    {
        return $this->belongsTo(CdmtExercice::class);
    }

    public function getSousProgrammesAvecActivites(): Collection
    {
        return $this->planStrategiqueEp->sousProgrammes()
            ->with([
                'programmeBudgetaire',
                'indicateurs',
                'actions' => fn($q) => $q->where('exercice_id', $this->exercice_id),
                'actions.activites' => fn($q) => $q->where('exercice_id', $this->exercice_id)
                    ->with(['indicateurs', 'taches' => fn($q2) => $q2->where('niveau', 'tache')]),
            ])
            ->get();
    }

    public function getTotalAe(): float
    {
        return $this->getSousProgrammesAvecActivites()
            ->flatMap->actions->flatMap->activites
            ->sum(fn($act) => $act->getTotalAe());
    }

    public function getTotalCp(): float
    {
        return $this->getSousProgrammesAvecActivites()
            ->flatMap->actions->flatMap->activites
            ->sum(fn($act) => $act->getTotalCp());
    }
}
