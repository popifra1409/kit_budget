<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lignes_bon_commande';

    protected $fillable = [
        'bon_commande_id',
        'nomenclature_id',
        'numero_ligne',
        'designation',
        'unite',
        'quantite',
        'prix_unitaire_ht',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ir',
        'taux_ir',
        'montant_ttc',
        'net_a_payer',
        'quantite_livree',
        'quantite_restante',
        'observations',
    ];

    protected $casts = [
        'quantite' => 'decimal:3',
        'prix_unitaire_ht' => 'decimal:2',
        'montant_ht' => 'decimal:2',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'taux_ir' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'net_a_payer' => 'decimal:2',
        'quantite_livree' => 'decimal:3',
        'quantite_restante' => 'decimal:3',
        'numero_ligne' => 'integer',
    ];

    /**
     * Boot - Calculer les montants automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        // Calculer lors de la création
        static::creating(function ($ligne) {
            $ligne->calculerMontants();
        });

        // Calculer lors de la mise à jour
        static::updating(function ($ligne) {
            $ligne->calculerMontants();
        });

        static::saved(function ($ligne) {
            // Recalculer les montants du BC parent
            if ($ligne->bonCommande) {
                $ligne->bonCommande->calculerMontants();
                $ligne->bonCommande->save();
            }
        });

        static::deleted(function ($ligne) {
            // Recalculer les montants du BC parent
            if ($ligne->bonCommande) {
                $ligne->bonCommande->calculerMontants();
                $ligne->bonCommande->save();
            }
        });
    }

    /**
     * Relation : Bon de commande parent
     */
    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    /**
     * Relation : Nomenclature budgétaire
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Calculer les montants automatiquement
     */
    public function calculerMontants(): void
    {
        // S'assurer que les valeurs sont des nombres
        $this->quantite = floatval($this->quantite ?? 0);
        $this->prix_unitaire_ht = floatval($this->prix_unitaire_ht ?? 0);
        $this->taux_tva = floatval($this->taux_tva ?? 19.25);
        $this->quantite_livree = floatval($this->quantite_livree ?? 0);

        // Montant HT = Quantité × Prix Unitaire HT
        $this->montant_ht = round($this->quantite * $this->prix_unitaire_ht, 2);

        // Montant TVA = Montant HT × (Taux TVA / 100)
        $this->montant_tva = round($this->montant_ht * ($this->taux_tva / 100), 2);

        // Montant TTC = Montant HT + Montant TVA
        $this->montant_ttc = round($this->montant_ht + $this->montant_tva, 2);

        // Calculer l'IR selon le barème (si taux_ir n'est pas défini ou est strictement null)
        // Important: si taux_ir = 0, c'est une exonération manuelle, on garde 0
        if ($this->taux_ir === null || $this->taux_ir === '') {
            $this->taux_ir = $this->calculerTauxIR();
        } else {
            // Conserver le taux saisi (peut être 0 pour exonération)
            $this->taux_ir = floatval($this->taux_ir);
        }

        $this->montant_ir = round($this->montant_ht * ($this->taux_ir / 100), 2);

        // Net à payer = TTC - IR
        $this->net_a_payer = round($this->montant_ttc - $this->montant_ir, 2);

        // Quantité restante = Quantité - Quantité livrée
        $this->quantite_restante = $this->quantite - $this->quantite_livree;
    }

    /**
     * Calculer le taux IR selon le barème camerounais
     * Par défaut : barème services (à adapter selon le type)
     */
    protected function calculerTauxIR(): float
    {
        // Barème IR Services (défaut)
        if ($this->montant_ht < 500000) {
            return 5.5;
        } elseif ($this->montant_ht < 3000000) {
            return 11.0;
        } else {
            return 15.0;
        }

        // Note : Pour fournitures/travaux, utiliser :
        // < 1M : 2.2%
        // 1M-5M : 5.5%
        // > 5M : 11%
    }

    /**
     * Enregistrer une livraison
     */
    public function enregistrerLivraison(float $quantite): void
    {
        if ($quantite > $this->quantite_restante) {
            throw new \Exception("La quantité livrée dépasse la quantité restante");
        }

        $this->quantite_livree += $quantite;
        $this->save();

        // Mettre à jour le statut du BC si toutes les lignes sont livrées
        $this->verifierStatutBC();
    }

    /**
     * Vérifier et mettre à jour le statut du BC
     */
    protected function verifierStatutBC(): void
    {
        $bc = $this->bonCommande;

        if (!$bc) {
            return;
        }

        $totalQuantite = $bc->lignes()->sum('quantite');
        $totalLivree = $bc->lignes()->sum('quantite_livree');

        if ($totalLivree == 0) {
            // Aucune livraison
            if ($bc->statut == 'engage') {
                $bc->statut = 'en_cours';
                $bc->save();
            }
        } elseif ($totalLivree < $totalQuantite) {
            // Livraison partielle
            $bc->statut = 'livre_partiellement';
            $bc->save();
        } else {
            // Livraison complète
            $bc->statut = 'livre';
            $bc->date_livraison_effective = now();
            $bc->save();
        }
    }

    /**
     * Obtenir le taux de livraison de cette ligne
     */
    public function getTauxLivraison(): float
    {
        if ($this->quantite == 0) {
            return 0;
        }

        return ($this->quantite_livree / $this->quantite) * 100;
    }

    /**
     * Vérifier si la ligne est complètement livrée
     */
    public function estLivree(): bool
    {
        return $this->quantite_livree >= $this->quantite;
    }
}
