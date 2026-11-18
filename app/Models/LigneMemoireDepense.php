<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasCorbeille;
use Illuminate\Database\Eloquent\SoftDeletes;

class LigneMemoireDepense extends Model
{
    use SoftDeletes, HasCorbeille;
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
     * Calculer tous les montants de la ligne, au FRANC ENTIER.
     *
     * Deux sources de vérité, selon le champ réellement saisi :
     * - NAP imposé ($napImpose, ou net_a_payer modifié par la saisie) :
     *     MHT = round(NAP / (1 - Taux IR/100))
     *     IR  = MHT - NAP      → le NAP affiché/Imprimé est exactement celui qui a été saisi ;
     *                             l'IR peut s'écarter du taux strict de ±1 F, c'est voulu.
     * - Prix unitaire :
     *     MHT = round(Qté × PU)
     *     IR  = round(MHT × Taux IR/100)
     *     NAP = MHT - IR       ← NAP = MHT - IR (et NON TTC - IR)
     *
     * TVA = round(MHT × Taux TVA/100) et TTC = MHT + TVA. Tous les montants stockés étant des
     * entiers de francs, la ligne, la ligne de total et le montant en toutes lettres concordent.
     */
    public function calculerMontants(bool $napImpose = false): void
    {
        $this->quantite = $this->quantite ?? 0;
        $this->prix_unitaire = $this->prix_unitaire ?? 0;
        $this->taux_tva = $this->taux_tva ?? 19.25;
        $this->taux_ir = $this->taux_ir ?? 5.5;

        // Locaux : les casts decimal:2 rendent les attributs en chaînes, le calcul reste en entiers.
        $quantite = (float) $this->quantite;
        $tauxTva  = (float) $this->taux_tva;
        $tauxIr   = (float) $this->taux_ir;
        $napSaisi = (int) round((float) ($this->net_a_payer ?? 0));

        if (($napImpose || $this->isDirty('net_a_payer')) && $napSaisi > 0 && $tauxIr < 100) {
            $mht = (int) round($napSaisi / (1 - ($tauxIr / 100)));
            $ir  = $mht - $napSaisi;
            $pu  = $quantite > 0 ? round($mht / $quantite, 2) : 0;
        } else {
            $mht = (int) round($quantite * (float) $this->prix_unitaire);
            $ir  = (int) round($mht * ($tauxIr / 100));
            $pu  = null;
        }

        $tva = (int) round($mht * ($tauxTva / 100));

        if ($pu !== null) {
            $this->prix_unitaire = $pu;
        }

        $this->montant_ht    = $mht;
        $this->montant_tva   = $tva;
        $this->montant_ttc   = $mht + $tva;
        $this->montant_ir    = $ir;
        $this->net_a_payer   = $mht - $ir;
    }

    /**
     * Calculer la ligne depuis le NAP saisi (calcul inversé), en imposant ce NAP.
     * 
     * Formules inversées :
     * - NAP = MHT - IR
     * - IR = MHT × (Taux IR / 100)
     * - Donc : NAP = MHT × (1 - Taux IR / 100)
     * - Donc : MHT = NAP / (1 - Taux IR / 100), arrêté au franc
     * - PU = MHT / Quantité (valeur dérivée, pour l'affichage et la reprise en mode PU)
     */
    public function calculerPrixUnitaireDepuisNAP(float $nap): void
    {
        $this->net_a_payer = (int) round($nap);
        $this->calculerMontants(napImpose: true);
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
