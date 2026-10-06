<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne de clôture : dotation actualisée = payé + report retenu + annulé. */
class ClotureLigne extends Model
{
    protected $table = 'clotures_lignes';

    protected $fillable = [
        'cloture_exercice_id',
        'ligne_budgetaire_id',
        'code',
        'libelle',
        'titre',
        'sous_programme_ep_id',
        'dotation',
        'engage',
        'liquide',
        'ordonnance',
        'paye',
        'engage_non_paye',
        'report_propose',
        'report_retenu',
        'annule',
        'motif_ecart',
    ];

    protected $casts = [
        'dotation' => 'decimal:2',
        'engage' => 'decimal:2',
        'liquide' => 'decimal:2',
        'ordonnance' => 'decimal:2',
        'paye' => 'decimal:2',
        'engage_non_paye' => 'decimal:2',
        'report_propose' => 'decimal:2',
        'report_retenu' => 'decimal:2',
        'annule' => 'decimal:2',
        'titre' => 'integer',
    ];

    public function cloture(): BelongsTo
    {
        return $this->belongsTo(ClotureExercice::class, 'cloture_exercice_id');
    }
    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class)->withoutGlobalScope('exercice');
    }
    public function sousProgramme(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_ep_id');
    }

    /** Engagements non soldés et non reportés : dette sans crédit en N+1. */
    public function getNonReporteNonPayeAttribute(): float
    {
        return max(0, (float) $this->engage_non_paye - (float) $this->report_retenu);
    }
}
