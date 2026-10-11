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

    public function consommations(): HasMany
    {
        return $this->hasMany(ProvisionConsommation::class, 'provision_ligne_regie_id');
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function peutEngager(float $montant): bool
    {
        return $this->montant_disponible >= $montant;
    }

    public function debiter(float $montant): void
    {
        if (!$this->peutEngager($montant)) {
            $autreEncaisse = (float) $this->ligneRegie
                ?->poolEncaisses()
                ->sum(fn($l) => max(0, (float) $l->montant_alloue - $l->montantConsommeReel()));

            throw new \Exception(
                "⛔ Encaisse insuffisant — veuillez approvisionner la régie.\n"
                    . 'Encaisse ' . ($this->decaissement?->numero ?? 'hors tranche')
                    . ' « ' . ($this->decaissement?->libelle_tranche ?? 'hors tranche') . ' » — nomenclature '
                    . ($this->ligneRegie?->nomenclature?->code ?? 'sans code') . "\n"
                    . "Disponible sur cet encaisse : " . number_format($this->montant_disponible, 0, ',', ' ') . " FCFA\n"
                    . "Demandé : " . number_format($montant, 0, ',', ' ') . " FCFA\n"
                    . ($autreEncaisse > (float) $this->montant_disponible
                        ? 'Total disponible sur tous les encaisses de cette nomenclature : '
                            . number_format($autreEncaisse, 0, ',', ' ')
                            . " FCFA — sélectionne un autre encaisse.\n"
                        : '')
                    . 'Solution : nouvelle DA sur la régie, puis « Accorder & Verser » une tranche.'
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
        // Dépenses validées/payées imputées sur CETTE provision
        $depenses = (float) $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        // Sommes réellement prises sur CETTE provision par les engagements BCR
        // (traçabilité provision_consommations). Compter le TTC complet de chaque
        // BCR lié re-solde à tort une provision déjà partiellement consommée, et
        // double une consommation répartie sur plusieurs décaissements.
        $consommations = (float) $this->consommations()->sum('montant');

        $consomme = $depenses + $consommations;

        $this->updateQuietly([
            'montant_consomme'   => $consomme,
            'montant_disponible' => (float) $this->montant_provisionne - $consomme,
        ]);
    }
}
