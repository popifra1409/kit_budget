<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportActiviteLigne extends Model
{
    protected $table = 'rapport_activite_lignes';

    protected $fillable = [
        'rapport_activite_periodique_id',
        'tache_id',
        'ligne_budgetaire_id',
        'nature',
        'libelle',
        'unite',
        'prevision',
        'realisation',
        'source_realisation',
        'quote_part',
        'realisation_actualisee_le',
    ];

    protected $casts = [
        'prevision' => 'decimal:2',
        'realisation' => 'decimal:2',
        'quote_part' => 'decimal:4',
        'realisation_actualisee_le' => 'datetime',
    ];

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function rapportActivitePeriodique(): BelongsTo
    {
        return $this->belongsTo(RapportActivitePeriodique::class);
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    public function getEcartAttribute(): float
    {
        return (float) $this->realisation - (float) $this->prevision;
    }

    public function getTauxRealisationAttribute(): ?float
    {
        if (!$this->prevision || (float) $this->prevision == 0) {
            return null;
        }

        return round(((float) $this->realisation / (float) $this->prevision) * 100, 1);
    }
}
