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
    public const NIVEAUX_AUTORISES = ['sous_programme_ep', 'activite'];

    protected $fillable = [
        'indicateurable_type',
        'indicateurable_id',
        'code',
        'libelle',
        'sens',
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

    /** Sens d'evolution souhaite de l'indicateur. */
    public const SENS = [
        'hausse' => '↑ À augmenter (une valeur plus élevée est meilleure)',
        'baisse' => '↓ À réduire (une valeur plus faible est meilleure)',
    ];

    /**
     * Taux d'atteinte de la cible (%), selon le sens de l'indicateur.
     * Source UNIQUE du calcul : utilisee par le RAP, la matrice d'arrimage et les tableaux.
     *
     *  - hausse : realise / cible  (ex. couverture vaccinale 90 / cible 80 = 112,5 %)
     *  - baisse : cible / realise  (ex. mortalite 2 % / cible 3 % = 150 %)
     *
     * Renvoie null si le calcul n'a pas de sens (valeur non numerique, cible nulle en hausse).
     */
    public function calculerTauxAtteinte(float|int|string|null $realise): ?float
    {
        if (!is_numeric($realise) || !is_numeric($this->valeur_cible)) {
            return null;
        }

        $realise = (float) $realise;
        $cible = (float) $this->valeur_cible;

        if ($this->estABaisser()) {
            // Valeur ramenee a zero : l'objectif de reduction est atteint
            if ($realise <= 0) {
                return 100.0;
            }

            return round(($cible / $realise) * 100, 1);
        }

        if ($cible <= 0) {
            return null;
        }

        return round(($realise / $cible) * 100, 1);
    }

    public function estABaisser(): bool
    {
        return ($this->sens ?? 'hausse') === 'baisse';
    }

    public function getSensSymbole(): string
    {
        return $this->estABaisser() ? '↓' : '↑';
    }
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
