<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneMemoireDepense extends Model
{
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
        'montant_ttc',
        'taux_ir',
        'montant_ir',
        'net_a_payer',
    ];

    protected $casts = [
        'quantite' => 'decimal:3',
        'prix_unitaire' => 'decimal:2',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'taux_ir' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'net_a_payer' => 'decimal:2',
    ];

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Calculer les montants avant la sauvegarde
        static::saving(function ($ligne) {
            $ligne->calculerMontants();
        });

        // Recalculer les totaux du mémoire après sauvegarde
        static::saved(function ($ligne) {
            if ($ligne->memoireDepense) {
                $ligne->memoireDepense->calculerTotaux();
                $ligne->memoireDepense->saveQuietly();
            }
        });

        // Recalculer les totaux du mémoire après suppression
        static::deleted(function ($ligne) {
            if ($ligne->memoireDepense) {
                $ligne->memoireDepense->calculerTotaux();
                $ligne->memoireDepense->saveQuietly();
            }
        });
    }

    /**
     * Relation : Mémoire de dépense parent
     */
    public function memoireDepense(): BelongsTo
    {
        return $this->belongsTo(MemoireDepense::class, 'memoire_depense_id');
    }

    /**
     * Calculer tous les montants de la ligne
     * 
     * Formules (conformes au fichier Excel) :
     * - MHT = Quantité × Prix Unitaire
     * - TVA = MHT × (Taux TVA / 100)
     * - TTC = MHT + TVA
     * - IR = MHT × (Taux IR / 100)
     * - NAP = MHT - IR  ← IMPORTANT : NAP = MHT - IR (et NON TTC - IR)
     */
    public function calculerMontants(): void
    {
        // S'assurer que les valeurs existent
        $this->quantite = $this->quantite ?? 0;
        $this->prix_unitaire = $this->prix_unitaire ?? 0;
        $this->taux_tva = $this->taux_tva ?? 19.25;
        $this->taux_ir = $this->taux_ir ?? 5.5;

        // Calcul du Montant HT
        $this->montant_ht = round($this->quantite * $this->prix_unitaire, 2);

        // Calcul de la TVA
        $this->montant_tva = round($this->montant_ht * ($this->taux_tva / 100), 2);

        // Calcul du TTC
        $this->montant_ttc = round($this->montant_ht + $this->montant_tva, 2);

        // Calcul de l'IR
        $this->montant_ir = round($this->montant_ht * ($this->taux_ir / 100), 2);

        // ✅ IMPORTANT : NAP = MHT - IR (formule conforme au fichier Excel)
        $this->net_a_payer = round($this->montant_ht - $this->montant_ir, 2);
    }

    /**
     * Calculer le prix unitaire depuis le NAP (calcul inversé)
     * 
     * Formules inversées :
     * - NAP = MHT - IR
     * - IR = MHT × (Taux IR / 100)
     * - Donc : NAP = MHT × (1 - Taux IR / 100)
     * - Donc : MHT = NAP / (1 - Taux IR / 100)
     * - PU = MHT / Quantité
     */
    public function calculerPrixUnitaireDepuisNAP(float $nap): void
    {
        if ($this->quantite <= 0) {
            $this->prix_unitaire = 0;
            return;
        }

        // Calcul inversé : NAP → MHT → PU
        $mht = $nap / (1 - ($this->taux_ir / 100));
        $this->prix_unitaire = round($mht / $this->quantite, 2);

        // Recalculer tous les montants avec le nouveau PU
        $this->calculerMontants();
    }

    /**
     * Accesseurs formatés
     */
    public function getMontantHtFormateAttribute(): string
    {
        return number_format($this->montant_ht, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTvaFormateAttribute(): string
    {
        return number_format($this->montant_tva, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTtcFormateAttribute(): string
    {
        return number_format($this->montant_ttc, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantIrFormateAttribute(): string
    {
        return number_format($this->montant_ir, 0, ',', ' ') . ' FCFA';
    }

    public function getNetAPayerFormateAttribute(): string
    {
        return number_format($this->net_a_payer, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Méthodes utilitaires
     */
    public function aPrixUnitaireValide(): bool
    {
        return $this->prix_unitaire > 0;
    }

    public function aQuantiteValide(): bool
    {
        return $this->quantite > 0;
    }

    public function estValide(): bool
    {
        return $this->aPrixUnitaireValide() && $this->aQuantiteValide();
    }
}
