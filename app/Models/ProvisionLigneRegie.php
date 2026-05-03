<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProvisionLigneRegie extends Model
{
    protected $table = 'provisions_lignes_regies';

    protected $fillable = [
        'decaissement_regie_id',
        'ligne_regie_avance_id',
        'montant_provisionne',
        'montant_consomme',
        'montant_disponible',
    ];

    protected $casts = [
        'montant_provisionne' => 'decimal:2',
        'montant_consomme'    => 'decimal:2',
        'montant_disponible'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($provision) {
            $provision->montant_disponible = $provision->montant_provisionne;
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function decaissement(): BelongsTo
    {
        return $this->belongsTo(DecaissementRegie::class, 'decaissement_regie_id');
    }

    public function ligneRegie(): BelongsTo
    {
        return $this->belongsTo(LigneRegieAvance::class, 'ligne_regie_avance_id');
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommandeRegie::class, 'provision_ligne_regie_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'provision_ligne_regie_id');
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function peutEngager(float $montant): bool
    {
        return $this->montant_disponible >= $montant;
    }

    public function debiter(float $montant): void
    {
        if (!$this->peutEngager($montant)) {
            throw new \Exception(
                "Provision insuffisante sur la ligne {$this->ligneRegie->nomenclature->code}.\n"
                . "Disponible : " . number_format($this->montant_disponible, 0, ',', ' ') . " FCFA\n"
                . "Demandé : "    . number_format($montant, 0, ',', ' ') . " FCFA"
            );
        }
        $this->updateQuietly([
            'montant_consomme'   => $this->montant_consomme + $montant,
            'montant_disponible' => $this->montant_disponible - $montant,
        ]);
    }

    public function crediter(float $montant): void
    {
        $this->updateQuietly([
            'montant_consomme'   => max(0, $this->montant_consomme - $montant),
            'montant_disponible' => $this->montant_disponible + $montant,
        ]);
    }

    public function recalculer(): void
    {
        $consomme = $this->bonsCommande()->where('engage', true)->sum('montant_ttc')
                  + $this->depenses()->whereNotIn('statut', ['annule'])->sum('montant_ttc');

        $this->updateQuietly([
            'montant_consomme'   => $consomme,
            'montant_disponible' => $this->montant_provisionne - $consomme,
        ]);
    }
}