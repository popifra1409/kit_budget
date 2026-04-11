<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasExercice;

class VirementBudgetaire extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'virements_budgetaires';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'ligne_source_id',
        'ligne_destination_id',
        'montant',
        'date_virement',
        'motif',
        'reference_decision',
        'statut',
        'valide_par',
        'date_validation',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_virement' => 'date',
        'date_validation' => 'datetime',
    ];

    /**
     * Boot - Générer le numéro automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($virement) {
            if (empty($virement->numero)) {
                $virement->numero = $virement->genererNumero();
            }
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
     * Relation : Ligne source (qui perd du budget)
     */
    public function ligneSource(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_source_id');
    }

    /**
     * Relation : Ligne destination (qui reçoit du budget)
     */
    public function ligneDestination(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_destination_id');
    }

    /**
     * Relation : Validateur
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : En attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope : Approuvés
     */
    public function scopeApprouves($query)
    {
        return $query->where('statut', 'approuve');
    }

    /**
     * Scope : Exécutés
     */
    public function scopeExecutes($query)
    {
        return $query->where('statut', 'execute');
    }

    /**
     * Générer le numéro de virement
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "VIR-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('VIR-%d-%05d', $annee, $nouveauNumero);
    }

    /**
     * Approuver le virement
     */
    public function approuver(User $user): void
    {
        $this->statut = 'approuve';
        $this->valide_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Exécuter le virement
     */
    public function executer(): void
    {
        if ($this->statut !== 'approuve') {
            throw new \Exception("Le virement doit d'abord être approuvé");
        }

        // Vérifier que la ligne source a assez de disponible
        $ligneSource = $this->ligneSource;
        if ($ligneSource->disponible_engagement < $this->montant) {
            throw new \Exception("Crédit insuffisant sur la ligne source. Disponible: {$ligneSource->disponible_engagement} FCFA");
        }

        // Mettre à jour la ligne source
        $ligneSource->virements_sortants += $this->montant;
        $ligneSource->save();

        // Mettre à jour la ligne destination
        $ligneDestination = $this->ligneDestination;
        $ligneDestination->virements_entrants += $this->montant;
        $ligneDestination->save();

        // Marquer le virement comme exécuté
        $this->statut = 'execute';
        $this->save();
    }

    /**
     * Rejeter le virement
     */
    public function rejeter(User $user): void
    {
        $this->statut = 'rejete';
        $this->valide_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Annuler un virement exécuté
     */
    public function annuler(): void
    {
        if ($this->statut !== 'execute') {
            throw new \Exception("Seuls les virements exécutés peuvent être annulés");
        }

        // Annuler sur la ligne source
        $ligneSource = $this->ligneSource;
        $ligneSource->virements_sortants -= $this->montant;
        $ligneSource->save();

        // Annuler sur la ligne destination
        $ligneDestination = $this->ligneDestination;
        $ligneDestination->virements_entrants -= $this->montant;
        $ligneDestination->save();

        // Marquer comme en attente
        $this->statut = 'en_attente';
        $this->save();
    }
}
