<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneMemoireDepense extends Model
{
    use HasFactory;

    protected $table = 'lignes_memoire_depense';

    protected $fillable = [
        'memoire_depense_id',
        'numero_ligne',
        'nature_depense',
        'quantite',
        'prix_unitaire',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'taux_ir',
        'montant_ir',
        'montant_ttc',
        'net_a_payer',
    ];

    protected $casts = [
        'numero_ligne' => 'integer',
        'quantite' => 'decimal:3',
        'prix_unitaire' => 'decimal:2',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'taux_ir' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'net_a_payer' => 'decimal:2',
    ];

    /**
     * Relations
     */
    public function memoireDepense(): BelongsTo
    {
        return $this->belongsTo(MemoireDepense::class);
    }

    /**
     * Calculer les montants automatiquement
     */
    public function calculerMontants(): void
    {
        // Montant HT = Quantité × Prix Unitaire
        $this->montant_ht = round($this->quantite * $this->prix_unitaire, 2);

        // Montant TVA = Montant HT × (Taux TVA / 100)
        $this->montant_tva = round($this->montant_ht * ($this->taux_tva / 100), 2);

        // Montant TTC = Montant HT + Montant TVA
        $this->montant_ttc = round($this->montant_ht + $this->montant_tva, 2);

        // Montant IR = Montant HT × (Taux IR / 100)
        $this->montant_ir = round($this->montant_ht * ($this->taux_ir / 100), 2);

        // Net à payer = TTC - IR
        $this->net_a_payer = round($this->montant_ttc - $this->montant_ir, 2);
    }

    /**
     * Événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ligne) {
            $ligne->calculerMontants();
        });

        static::saved(function ($ligne) {
            // Recalculer les totaux du mémoire parent
            if ($ligne->memoireDepense) {
                $ligne->memoireDepense->calculerTotaux();
                $ligne->memoireDepense->saveQuietly();
            }
        });

        static::deleted(function ($ligne) {
            // Recalculer les totaux du mémoire parent
            if ($ligne->memoireDepense) {
                $ligne->memoireDepense->calculerTotaux();
                $ligne->memoireDepense->saveQuietly();
            }
        });
    }
}
