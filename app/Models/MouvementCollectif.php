<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCollectif extends Model
{
    use HasFactory;

    protected $table = 'mouvements_collectifs';

    protected $fillable = [
        'collectif_budgetaire_id',
        'type',
        'ligne_depense_id',
        'ligne_recette_id',
        'nouvelle_ligne_depense_id',
        'nouvelle_ligne_recette_id',
        // ✅ Colonnes virement
        'ligne_source_id',
        'ligne_destination_id',
        'montant_modification',
        'motif',
    ];

    protected $casts = [
        'montant_modification' => 'decimal:2',
    ];

    public function collectif(): BelongsTo
    {
        return $this->belongsTo(CollectifBudgetaire::class);
    }

    // Relations pour les lignes existantes
    public function ligneDepense(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_depense_id');
    }

    public function ligneRecette()
    {
        return $this->belongsTo(LignePrevisionRecette::class, 'ligne_recette_id');
    }

    // Relations pour les nouvelles lignes créées
    public function nouvelleLigneDepense(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'nouvelle_ligne_depense_id');
    }

    public function nouvelleLigneRecette()
    {
        return $this->belongsTo(LignePrevisionRecette::class, 'nouvelle_ligne_recette_id');
    }

    public function ligneSource()
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_source_id');
    }

    public function ligneDestination()
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_destination_id');
    }

    /**
     * Appliquer ce mouvement.
     */
    public function appliquer(): void
    {
        if ($this->type === 'virement') {
            // Exécuter le virement sur les lignes source/destination
            $source = $this->ligneSource;
            $dest   = $this->ligneDestination;
            $montant = $this->montant_modification;
            if ($source->disponible_engagement < $montant) {
                throw new \Exception("Fonds insuffisants sur la source");
            }
            $source->virements_sortants += $montant;
            $source->save();
            $dest->virements_entrants += $montant;
            $dest->save();
        } else {
            if ($this->type === 'depense') {
                if ($this->nouvelle_ligne_depense_id) {
                    // La nouvelle ligne a déjà été créée (lors de la création du mouvement)
                    // On marque juste qu'elle est issue du collectif
                    $ligne = $this->nouvelleLigneDepense;
                    if ($ligne) {
                        $ligne->est_issue_collectif = true;
                        $ligne->collectif_creation_id = $this->collectif_id;
                        $ligne->save();
                    }
                } elseif ($this->ligne_depense_id) {
                    $ligne = $this->ligneDepense;
                    if ($ligne) {
                        // Appliquer la modification
                        $ligne->budget_rectifie += $this->montant_modification;
                        $ligne->save();
                    }
                }
            } else { // recette
                if ($this->nouvelle_ligne_recette_id) {
                    $ligne = $this->nouvelleLigneRecette;
                    if ($ligne) {
                        $ligne->est_issue_collectif = true;
                        $ligne->collectif_creation_id = $this->collectif_id;
                        $ligne->save();
                    }
                } elseif ($this->ligne_recette_id) {
                    $ligne = $this->ligneRecette;
                    if ($ligne) {
                        $ligne->montant_rectifie += $this->montant_modification;
                        $ligne->save();
                    }
                }
            }
        }
    }

    /**
     * Annuler ce mouvement.
     */
    public function annuler(): void
    {
        if ($this->type === 'depense') {
            if ($this->nouvelle_ligne_depense_id) {
                // Supprimer la ligne créée ? Ou la désactiver ?
                $ligne = $this->nouvelleLigneDepense;
                if ($ligne) {
                    // Option: on pourrait la supprimer, mais on préfère la marquer comme issue d'un collectif annulé
                    $ligne->est_issue_collectif = false;
                    $ligne->collectif_creation_id = null;
                    $ligne->save();
                    // Ou on la supprime purement et simplement
                    // $ligne->delete();
                }
            } elseif ($this->ligne_depense_id) {
                $ligne = $this->ligneDepense;
                if ($ligne) {
                    $ligne->budget_rectifie -= $this->montant_modification;
                    $ligne->save();
                }
            }
        } else {
            if ($this->nouvelle_ligne_recette_id) {
                $ligne = $this->nouvelleLigneRecette;
                if ($ligne) {
                    $ligne->est_issue_collectif = false;
                    $ligne->collectif_creation_id = null;
                    $ligne->save();
                }
            } elseif ($this->ligne_recette_id) {
                $ligne = $this->ligneRecette;
                if ($ligne) {
                    $ligne->montant_rectifie -= $this->montant_modification;
                    $ligne->save();
                }
            }
        }
    }
}
