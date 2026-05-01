<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommandeRegie extends Model
{
    protected $table = 'lignes_bons_commande_regies';

    protected $fillable = [
        'bon_commande_regie_id',
        'numero_ligne',
        'designation',
        'quantite',
        'unite',
        'prix_unitaire_ht',
        'taux_tva',
        'montant_tva',
        'montant_ht',
        'montant_ttc',
        'taux_ir',
        'montant_ir',
        'net_a_payer',
        'observations',
    ];

    protected $casts = [
        'quantite'         => 'decimal:2',
        'prix_unitaire_ht' => 'decimal:2',
        'montant_ht'       => 'decimal:2',
        'montant_tva'      => 'decimal:2',
        'montant_ttc'      => 'decimal:2',
        'montant_ir'       => 'decimal:2',
        'net_a_payer'      => 'decimal:2',
        'taux_tva'         => 'decimal:2',
        'taux_ir'          => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function ($ligne) {
            $ligne->bonCommandeRegie?->recalculerTotaux();
        });
        static::deleted(function ($ligne) {
            $ligne->bonCommandeRegie?->recalculerTotaux();
        });
    }

    public function bonCommandeRegie(): BelongsTo
    {
        return $this->belongsTo(BonCommandeRegie::class, 'bon_commande_regie_id');
    }

    // Calculer montants depuis prix et taux
    public function calculer(): void
    {
        $ht  = round($this->quantite * $this->prix_unitaire_ht, 2);
        $tva = round($ht * ($this->taux_tva / 100), 2);
        $ir  = round($ht * ($this->taux_ir  / 100), 2);

        $this->montant_ht  = $ht;
        $this->montant_tva = $tva;
        $this->montant_ttc = round($ht + $tva, 2);
        $this->montant_ir  = $ir;
        $this->net_a_payer = round($ht - $ir, 2);
    }
}
