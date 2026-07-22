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
        'montant_modification',
        'motif',
        'virement_budgetaire_id', // ✅ VirementBudgetaire lié
    ];

    protected $casts = [
        'montant_modification' => 'decimal:2',
    ];

    public function collectif(): BelongsTo
    {
        return $this->belongsTo(CollectifBudgetaire::class, 'collectif_budgetaire_id');
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

    public function virementBudgetaire()
    {
        return $this->belongsTo(VirementBudgetaire::class, 'virement_budgetaire_id');
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
    public function appliquer(?User $user = null): void
    {
        // ✅ Garde — vérifier que le disponible ne sera pas négatif
        if ($this->type === 'depense' && $this->ligne_depense_id) {
            $ligne       = $this->ligneDepense;
            $nouveauRectifie = \App\Filament\Budget\Resources\FicheControleEngagementsResource::getBudgetRectifieReel($ligne);
            $disponible  = $nouveauRectifie - (float) $ligne->engage;
            if ($disponible < 0 && $this->montant_modification > 0) {
                // Réduction dangereuse — bloquer
                throw new \Exception(
                    "Ce mouvement rendrait le disponible négatif sur la ligne "
                        . ($ligne->nomenclature?->code ?? $ligne->id)
                        . " (disponible prévu : " . number_format($disponible, 0, ',', ' ') . " FCFA)"
                );
            }
        }

        if ($this->type === 'virement') {
            // ✅ Déléguer à VirementBudgetaire::executer()
            if ($this->virement_budgetaire_id && $this->virementBudgetaire) {
                $virement = $this->virementBudgetaire;

                // Un virement issu d'un mouvement collectif est auto-approuvé
                // au moment de l'adoption : pas de validation manuelle intermédiaire.
                if ($virement->statut === 'en_attente') {
                    $virement->statut = 'approuve';
                    $virement->valide_par = $user?->id;
                    $virement->date_validation = now();
                    $virement->save();

                    ActivityLog::logAction($virement, 'valider', [
                        'ancien_statut'  => 'en_attente',
                        'nouveau_statut' => 'approuve',
                        'approuve_par'   => $user?->name ?? 'Système (adoption collectif)',
                        'montant'        => $virement->montant,
                        'contexte'       => 'Auto-approuvé via adoption du collectif budgétaire',
                    ]);
                }

                $virement->executer();
            } else {
                // Fallback direct si pas de VirementBudgetaire lié
                $source  = $this->ligneSource;
                $dest    = $this->ligneDestination;
                $montant = $this->montant_modification;
                if ($source->disponible_engagement < $montant) {
                    throw new \Exception("Fonds insuffisants sur la ligne source");
                }
                $source->virements_sortants += $montant;
                $source->save();
                $dest->virements_entrants += $montant;
                $dest->save();
            }
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
                        // ✅ Recalcul complet — budget_rectifie = initial + Σ collectifs adoptés
                        \App\Filament\Budget\Resources\FicheControleEngagementsResource::recalculerLigne($ligne);
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
        // ✅ Garde — vérifier que l'annulation ne rend pas le disponible négatif
        if ($this->type === 'depense' && $this->ligne_depense_id && $this->montant_modification > 0) {
            $ligne = $this->ligneDepense;
            if ($ligne) {
                // Après annulation, budget_rectifie sera réduit du montant du mouvement
                $futureRectifie = (float) $ligne->budget_rectifie - (float) $this->montant_modification;
                $futureDisponible = $futureRectifie - (float) $ligne->engage;
                if ($futureDisponible < 0) {
                    throw new \Exception(
                        "Impossible d'annuler : le disponible de la ligne "
                            . ($ligne->nomenclature?->code ?? $ligne->id)
                            . " deviendrait négatif ("
                            . number_format($futureDisponible, 0, ',', ' ')
                            . " FCFA). Annulez ou réduisez les engagements d'abord."
                    );
                }
            }
        }

        // ✅ Virement : rejeter le VirementBudgetaire lié
        if ($this->type === 'virement') {
            if ($this->virement_budgetaire_id && $this->virementBudgetaire) {
                $virement = $this->virementBudgetaire;
                if ($virement->statut === 'execute') {
                    // Annuler les effets sur les lignes
                    $virement->annuler(); // remet en en_attente
                }
                // Puis marquer comme rejeté
                $virement->statut    = 'rejete';
                $virement->save();
            }
            return;
        }

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
                    // ✅ Recalcul complet — collectif annulé sera exclu du SUM
                    \App\Filament\Budget\Resources\FicheControleEngagementsResource::recalculerLigne($ligne);
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
