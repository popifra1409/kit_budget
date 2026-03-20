<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneBudgetaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lignes_budgetaires';

    protected $fillable = [
        'budget_id',
        'nomenclature_id',
        'budget_initial',
        'virements_entrants',
        'virements_sortants',
        'budget_rectifie',
        'engage',
        'ordonne',
        'liquide',
        'paye',
        'disponible_engagement',
        'disponible_ordonnancement',
        'observations',
    ];

    protected $casts = [
        'budget_initial' => 'decimal:2',
        'virements_entrants' => 'decimal:2',
        'virements_sortants' => 'decimal:2',
        'budget_rectifie' => 'decimal:2',
        'engage' => 'decimal:2',
        'ordonne' => 'decimal:2',
        'liquide' => 'decimal:2',
        'paye' => 'decimal:2',
        'disponible_engagement' => 'decimal:2',
        'disponible_ordonnancement' => 'decimal:2',
    ];

    protected $appends = [
        'total_engage',
        'nombre_engagements',
        'taux_consommation',
    ];

    /**
     * Boot - Calculer automatiquement les montants
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ligne) {
            $ligne->calculerMontants();
        });
    }

    /**
     * Relation : Les engagements de cette ligne budgétaire
     */

    public function engagements()
    {
        $engagementIds = \DB::table('lignes_engagement')
            ->where('nomenclature_id', $this->nomenclature_id)
            ->pluck('engagement_id')
            ->unique();

        return \App\Models\Engagement::query()
            ->whereIn('id', $engagementIds);
    }

    public function lignesEngagement()
    {
        return $this->hasMany(\App\Models\LigneEngagement::class, 'nomenclature_id', 'nomenclature_id');
    }

    /**
     * Montant total engagé via lignes_engagement
     */
    public function getTotalEngageViaLignesAttribute(): float
    {
        return (float) \DB::table('lignes_engagement')
            ->where('nomenclature_id', $this->nomenclature_id)
            ->sum('montant');
    }
    // public function engagements()
    // {
    //     // Trouver le nom de la colonne pour BonCommande
    //     $bcColumn = null;
    //     $possibleColumns = [
    //         'ligne_budgetaire_id',
    //         'lignebudgetaire_id',
    //         'ligne_budget_id',
    //         'budget_ligne_id',
    //         'budgetaire_ligne_id',
    //     ];

    //     $bcTableColumns = \Schema::getColumnListing('bon_commandes');
    //     foreach ($possibleColumns as $col) {
    //         if (in_array($col, $bcTableColumns)) {
    //             $bcColumn = $col;
    //             break;
    //         }
    //     }

    //     // Trouver le nom de la colonne pour DecisionAdministrative
    //     $daColumn = null;
    //     $daTableColumns = \Schema::getColumnListing('decisions_administratives');
    //     foreach ($possibleColumns as $col) {
    //         if (in_array($col, $daTableColumns)) {
    //             $daColumn = $col;
    //             break;
    //         }
    //     }

    //     $id = $this->id;

    //     return \App\Models\Engagement::query()
    //         ->where(function ($q) use ($id, $bcColumn, $daColumn) {
    //             // Via BonCommande si la colonne existe
    //             if ($bcColumn) {
    //                 $q->whereHasMorph('engageable', [\App\Models\BonCommande::class], function ($q2) use ($id, $bcColumn) {
    //                     $q2->where($bcColumn, $id);
    //                 });
    //             }

    //             // Via DecisionAdministrative si la colonne existe
    //             if ($daColumn) {
    //                 $q->orWhereHasMorph('engageable', [\App\Models\DecisionAdministrative::class], function ($q2) use ($id, $daColumn) {
    //                     $q2->where($daColumn, $id);
    //                 });
    //             }
    //         });
    // }

    public function bonCommandes()
    {
        // Liste des noms de colonnes possibles
        $possibleColumns = [
            'ligne_budgetaire_id',
            'lignebudgetaire_id',
            'ligne_budget_id',
            'budget_ligne_id',
            'budgetaire_ligne_id',
        ];

        // Chercher quelle colonne existe
        $tableColumns = \Schema::getColumnListing('bon_commandes');
        foreach ($possibleColumns as $col) {
            if (in_array($col, $tableColumns)) {
                return $this->hasMany(\App\Models\BonCommande::class, $col);
            }
        }

        // Si aucune trouvée, retourner une relation vide
        return $this->hasMany(\App\Models\BonCommande::class, 'ligne_budgetaire_id');
    }

    public function decisionsAdministratives()
    {
        // Liste des noms de colonnes possibles
        $possibleColumns = [
            'ligne_budgetaire_id',
            'lignebudgetaire_id',
            'ligne_budget_id',
            'budget_ligne_id',
            'budgetaire_ligne_id',
        ];

        // Chercher quelle colonne existe
        $tableColumns = \Schema::getColumnListing('bon_commandes');
        foreach ($possibleColumns as $col) {
            if (in_array($col, $tableColumns)) {
                return $this->hasMany(\App\Models\BonCommande::class, $col);
            }
        }

        // Si aucune trouvée, retourner une relation vide
        return $this->hasMany(\App\Models\BonCommande::class, 'ligne_budgetaire_id');
    }

    /**
     * Relation : Budget parent
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Relation : Nomenclature budgétaire
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Relation : Virements sources (départs)
     */
    public function virementsSource(): HasMany
    {
        return $this->hasMany(VirementBudgetaire::class, 'ligne_source_id');
    }

    /**
     * Relation : Virements destination (arrivées)
     */
    public function virementsDestination(): HasMany
    {
        return $this->hasMany(VirementBudgetaire::class, 'ligne_destination_id');
    }

    /**
     * Calculer les montants automatiquement
     */
    public function calculerMontants(): void
    {
        // Budget rectifié = initial + virements entrants - virements sortants
        $this->budget_rectifie = $this->budget_initial + $this->virements_entrants - $this->virements_sortants;

        // Disponible pour engagement = budget rectifié - engagé
        $this->disponible_engagement = $this->budget_rectifie - $this->engage;

        // Disponible pour ordonnancement = engagé - ordonné
        $this->disponible_ordonnancement = $this->engage - $this->ordonne;
    }

    /**
     * Vérifier si un engagement est possible
     */
    public function peutEngager(float $montant): bool
    {
        return $this->disponible_engagement >= $montant;
    }

    /**
     * Vérifier si un ordonnancement est possible
     */
    public function peutOrdonner(float $montant): bool
    {
        return $this->disponible_ordonnancement >= $montant;
    }

    /**
     * Enregistrer un engagement
     */
    public function enregistrerEngagement(float $montant): void
    {
        if (!$this->peutEngager($montant)) {
            throw new \Exception("Crédit insuffisant pour engagement. Disponible: {$this->disponible_engagement} FCFA");
        }

        $this->engage += $montant;
        $this->save();
    }

    /**
     * Enregistrer un ordonnancement
     */
    public function enregistrerOrdonnancement(float $montant): void
    {
        if (!$this->peutOrdonner($montant)) {
            throw new \Exception("Crédit insuffisant pour ordonnancement. Disponible: {$this->disponible_ordonnancement} FCFA");
        }

        $this->ordonne += $montant;
        $this->save();
    }

    /**
     * Enregistrer une liquidation
     */
    public function enregistrerLiquidation(float $montant): void
    {
        $this->liquide += $montant;
        $this->save();
    }

    /**
     * Enregistrer un paiement
     */
    public function enregistrerPaiement(float $montant): void
    {
        $this->paye += $montant;
        $this->save();
    }

    /**
     * Annuler un engagement
     */
    public function annulerEngagement(float $montant): void
    {
        $this->engage -= $montant;
        $this->save();
    }

    /**
     * Taux d'engagement de la ligne
     */
    public function getTauxEngagement(): float
    {
        if ($this->budget_rectifie == 0) {
            return 0;
        }
        return ($this->engage / $this->budget_rectifie) * 100;
    }

    /**
     * Taux d'exécution de la ligne
     */
    public function getTauxExecution(): float
    {
        if ($this->budget_rectifie == 0) {
            return 0;
        }
        return ($this->liquide / $this->budget_rectifie) * 100;
    }

    // ============================================
// ACCESSEURS À AJOUTER AU MODÈLE LigneBudgetaire
// Pour optimiser les calculs d'engagements
// ============================================

    /**
     * Attribut calculé : Total des montants engagés
     * 
     * @return float
     */
    public function getTotalEngageAttribute(): float
    {
        return 0;
    }

    /**
     * Attribut calculé : Nombre total d'engagements
     * 
     * @return int
     */
    public function getNombreEngagementsAttribute(): int
    {
        return 0;
    }

    /**
     * Attribut calculé : Taux de consommation en pourcentage
     * 
     * @return float
     */
    public function getTauxConsommationAttribute(): float
    {
        return 0;
    }

    /**
     * Attribut calculé : Collection des engagements
     * (pour utilisation dans les vues)
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getEngagementsCollectionAttribute()
    {
        return $this->engagements()->get();
    }
}
