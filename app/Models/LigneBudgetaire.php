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
}
