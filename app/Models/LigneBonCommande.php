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
        'reference_mercuriale_id',
        'reference_personnalisee',
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
            // Si numero_ligne pas défini, calculer automatiquement
            if (empty($ligne->numero_ligne)) {
                $max = static::where('bon_commande_id', $ligne->bon_commande_id)
                    ->max('numero_ligne') ?? 0;
                $ligne->numero_ligne = $max + 1;
            }
            $ligne->calculerMontants();
        });

        // Calculer lors de la mise à jour
        static::updating(function ($ligne) {
            $ligne->calculerMontants();
        });

        static::saved(function ($ligne) {
            // Recalculer les montants du BC parent (sans déclencher une boucle)
            if ($ligne->bonCommande && !$ligne->bonCommande->isDirty()) {
                $ligne->bonCommande->calculerMontants();
                $ligne->bonCommande->saveQuietly();
            }
        });

        static::deleted(function ($ligne) {
            // Recalculer les montants du BC parent
            if ($ligne->bonCommande) {
                $ligne->bonCommande->calculerMontants();
                $ligne->bonCommande->saveQuietly();
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
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    /**
     * Relation : Référence mercuriale
     */
    public function referenceMercuriale(): BelongsTo
    {
        return $this->belongsTo(ReferenceMercuriale::class, 'reference_mercuriale_id');
    }

    // ===== ACCESSEURS =====

    /**
     * Obtenir la référence à afficher (mercuriale ou personnalisée)
     */
    public function getReferenceAttribute(): ?string
    {
        // Si référence mercuriale, retourner son code
        if ($this->referenceMercuriale) {
            return $this->referenceMercuriale->code_reference;
        }

        // Sinon retourner la référence personnalisée
        return $this->reference_personnalisee;
    }

    /**
     * Calculer les montants automatiquement
     * MÉTHODE PRINCIPALE appelée par les événements du modèle
     */
    public function calculerMontants(): void
    {
        // S'assurer que les valeurs sont des nombres
        $this->quantite = floatval($this->quantite ?? 0);
        $this->prix_unitaire_ht = floatval($this->prix_unitaire_ht ?? 0);
        $this->quantite_livree = floatval($this->quantite_livree ?? 0);

        // Montant HT = Quantité × Prix Unitaire HT
        $this->montant_ht = round($this->quantite * $this->prix_unitaire_ht, 2);

        // ===== GESTION DE LA TVA =====
        // Vérifier si le BC parent est exonéré de TVA
        $bonCommande = $this->bonCommande;
        if ($bonCommande && $bonCommande->exonere_tva) {
            // Forcer le taux TVA à 0 si le BC est exonéré
            $this->taux_tva = 0;
            $this->montant_tva = 0;
        } else {
            // Utiliser le taux TVA défini ou le taux par défaut
            $this->taux_tva = floatval($this->taux_tva ?? 19.25);
            $this->montant_tva = round($this->montant_ht * ($this->taux_tva / 100), 2);
        }

        // Montant TTC = Montant HT + Montant TVA
        $this->montant_ttc = round($this->montant_ht + $this->montant_tva, 2);

        // ===== GESTION DE L'IR =====
        // Calculer l'IR selon le type d'engagement et le régime fiscal
        if ($this->taux_ir === null || $this->taux_ir === '') {
            // Calcul automatique de l'IR
            $this->taux_ir = $this->calculerTauxIRAutomatique();
        } else {
            // Conserver le taux saisi (peut être 0 pour exonération manuelle)
            $this->taux_ir = floatval($this->taux_ir);
        }

        $this->montant_ir = round($this->montant_ht * ($this->taux_ir / 100), 2);

        // ===== NET À PERCEVOIR =====
        // Net à percevoir = Montant HT - IR (ou TTC - IR selon votre logique métier)
        // Option 1 : Net = HT - IR (montant sans TVA après retenue IR)
        $this->net_a_payer = round($this->montant_ht - $this->montant_ir, 2); // ← CHANGÉ

        // Option 2 : Net = TTC - IR (si l'IR doit être déduit du TTC)
        // $this->net_a_payer = round($this->montant_ttc - $this->montant_ir, 2);

        // Quantité restante = Quantité - Quantité livrée
        $this->quantite_restante = $this->quantite - $this->quantite_livree;
    }

    /**
     * Recalculer les montants de la ligne
     * MÉTHODE PUBLIQUE pour recalcul manuel (utilisée par l'Observer)
     */
    public function recalculerMontants(): void
    {
        $this->calculerMontants();
    }

    /**
     * Calculer le taux IR automatiquement selon le type d'engagement et le régime fiscal
     */
    protected function calculerTauxIRAutomatique(): float
    {
        $bonCommande = $this->bonCommande;

        // Si pas de BC ou pas de type d'engagement, utiliser le barème par défaut
        if (!$bonCommande || !$bonCommande->typeEngagement || !$bonCommande->fournisseur) {
            return $this->calculerTauxIRParDefaut();
        }

        $typeEngagement = $bonCommande->typeEngagement;
        $fournisseur = $bonCommande->fournisseur;

        // Charger le régime fiscal si nécessaire
        if (!$fournisseur->relationLoaded('regimeFiscal')) {
            $fournisseur->load('regimeFiscal');
        }

        // Utiliser la méthode du type d'engagement pour calculer le taux IR
        if ($fournisseur->regimeFiscal) {
            return $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
        }

        // Fallback : barème par défaut
        return $this->calculerTauxIRParDefaut();
    }

    /**
     * Calculer le taux IR selon le barème camerounais par défaut
     * Barème services (à adapter selon le type de prestation)
     */
    protected function calculerTauxIRParDefaut(): float
    {
        // Barème IR Services (défaut)
        if ($this->montant_ht < 500000) {
            return 5.5;
        } elseif ($this->montant_ht < 3000000) {
            return 11.0;
        } else {
            return 15.0;
        }
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
                $bc->saveQuietly();
            }
        } elseif ($totalLivree < $totalQuantite) {
            // Livraison partielle
            $bc->statut = 'livre_partiellement';
            $bc->saveQuietly();
        } else {
            // Livraison complète
            $bc->statut = 'livre';
            $bc->date_livraison_effective = now();
            $bc->saveQuietly();
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

    /**
     * Forcer le recalcul avec exonération de TVA
     * Utilisé par l'Observer du BonCommande
     */
    public function appliquerExonerationTVA(): void
    {
        $this->taux_tva = 0;
        $this->montant_tva = 0;
        $this->recalculerMontants();
    }

    /**
     * Restaurer le taux de TVA normal
     */
    public function restaurerTVA(float $tauxTVA = 19.25): void
    {
        $this->taux_tva = $tauxTVA;
        $this->recalculerMontants();
    }
}
