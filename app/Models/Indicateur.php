<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicateur extends Model
{
    use SoftDeletes;

    protected $table = 'indicateurs';

    /** Niveaux autorises pour le rattachement (alias du morph map) */
    public const NIVEAUX_AUTORISES = ['sous_programme_ep', 'action_sous_programme'];

    protected $fillable = [
        'indicateurable_type',
        'indicateurable_id',
        'code',
        'libelle',
        'objectif_associe',
        'unite_mesure',
        'mode_calcul',
        'periodicite_mesure',
        'valeur_reference',
        'annee_reference',
        'valeur_cible',
        'annee_cible',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'valeur_reference' => 'decimal:2',
        'valeur_cible' => 'decimal:2',
        'annee_reference' => 'integer',
        'annee_cible' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();

            if (!in_array($model->indicateurable_type, self::NIVEAUX_AUTORISES, true)) {
                throw new \InvalidArgumentException(
                    "Un indicateur ne peut être rattaché qu'à : " . implode(', ', self::NIVEAUX_AUTORISES)
                );
            }
        });
    }

    public function indicateurable(): MorphTo
    {
        return $this->morphTo();
    }

    public function valeurs(): HasMany
    {
        return $this->hasMany(ValeurIndicateur::class);
    }

    public function derniereValeur(): ?ValeurIndicateur
    {
        return $this->valeurs()->orderByDesc('periode')->first();
    }
}
