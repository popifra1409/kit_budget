<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Extrant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'activite_id',
        'libelle',
        'description',
        'quantite_prevue',
        'unite_mesure',
        'quantite_realisee',
        'statut',
        'date_realisation_prevue',
        'date_realisation_effective',
        'commentaire',
        'created_by',
    ];

    protected $casts = [
        'quantite_prevue' => 'decimal:2',
        'quantite_realisee' => 'decimal:2',
        'date_realisation_prevue' => 'date',
        'date_realisation_effective' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
        });
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    /**
     * Taux de realisation de l'extrant (quantite realisee / quantite prevue).
     * Retourne null si non chiffrable (extrant purement qualitatif).
     */
    public function getTauxRealisation(): ?float
    {
        if (!$this->quantite_prevue || $this->quantite_prevue == 0) {
            return null;
        }

        return round((($this->quantite_realisee ?? 0) / $this->quantite_prevue) * 100, 1);
    }

    public function marquerRealise(?float $quantiteRealisee = null): void
    {
        $this->update([
            'statut' => 'realise',
            'quantite_realisee' => $quantiteRealisee ?? $this->quantite_prevue,
            'date_realisation_effective' => now(),
        ]);
    }
}
