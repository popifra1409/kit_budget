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
}
