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
        'montant_ttc',
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
        'montant_ttc' => 'decimal:2',
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

        static::saving(function ($ligne) {
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
        // Montant HT = Quantité × Prix Unitaire HT
        $this->montant_ht = $this->quantite * $this->prix_unitaire_ht;

        // Montant TVA = Montant HT × (Taux TVA / 100)
        $this->montant_tva = $this->montant_ht * ($this->taux_tva / 100);

        // Montant TTC = Montant HT + Montant TVA
        $this->montant_ttc = $this->montant_ht + $this->montant_tva;

        // Quantité restante = Quantité - Quantité livrée
        $this->quantite_restante = $this->quantite - $this->quantite_livree;
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
