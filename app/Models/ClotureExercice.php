<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Clôture d'un exercice : reports et annulations (logique : App\Services\Budget\ClotureExerciceService). */
class ClotureExercice extends Model
{
    public const STATUTS = [
        'preparation' => 'En préparation',
        'arretee'     => 'Arrêté de report signé',
        'avis_ca'     => 'Avis conforme du CA',
        'reprise'     => 'Reports repris en N+1',
    ];

    protected $table = 'clotures_exercice';

    protected $fillable = [
        'exercice_id',
        'budget_id',
        'statut',
        'reference_arrete',
        'date_arrete',
        'piece_arrete',
        'arrete_par',
        'avis_ca',
        'reference_avis_ca',
        'date_avis_ca',
        'piece_avis_ca',
        'report_fonctionnement_autorise',
        'date_calcul',
        'totaux',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'date_arrete' => 'date',
        'date_avis_ca' => 'date',
        'date_calcul' => 'datetime',
        'totaux' => 'array',
        'report_fonctionnement_autorise' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $c) => $c->created_by ??= auth()->id());
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withoutGlobalScope('exercice');
    }
    public function lignes(): HasMany
    {
        return $this->hasMany(ClotureLigne::class);
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'preparation';
    }

    public function getStatutLabelAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}
