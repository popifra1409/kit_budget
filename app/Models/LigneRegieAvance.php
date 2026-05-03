<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneRegieAvance extends Model
{
    protected $table = 'lignes_regies_avances';

    protected $fillable = [
        'regie_avance_id',
        'nomenclature_id',
        'ligne_budgetaire_id',
        'montant_alloue',
        'montant_consomme',
        'montant_disponible',
    ];

    protected $casts = [
        'montant_alloue'    => 'decimal:2',
        'montant_consomme'  => 'decimal:2',
        'montant_disponible' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($ligne) {
            $ligne->montant_disponible = $ligne->montant_alloue;
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_budgetaire_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'ligne_regie_avance_id');
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function recalculerMontants(): void
    {
        // ✅ Sommer toutes les dépenses validées/payées + BCR engagés
        $depenses = $this->depenses()
            ->whereNotIn('statut', ['annule'])
            ->sum('montant_ttc');

        $bonsCommande = \App\Models\BonCommandeRegie::where('ligne_regie_avance_id', $this->id)
            ->where('engage', true)
            ->whereNotIn('statut', ['annule'])
            ->sum('montant_ttc');

        $consomme = $depenses + $bonsCommande;

        $this->updateQuietly([
            'montant_consomme'   => $consomme,
            'montant_disponible' => $this->montant_alloue - $consomme,
        ]);
    }

    public function peutDepenser(float $montant): bool
    {
        return $this->montant_disponible >= $montant;
    }

    public function getTauxConsommationAttribute(): float
    {
        if ($this->montant_alloue <= 0) return 0;
        return round(($this->montant_consomme / $this->montant_alloue) * 100, 2);
    }
}
