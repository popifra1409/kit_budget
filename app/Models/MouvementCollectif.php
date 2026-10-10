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
        'nomenclature_creee_id',
        'montant_modification',
        'motif',
        'virement_budgetaire_id', // ✅ VirementBudgetaire lié
        'statut',
        'date_annulation',
        'annule_par',
    ];

    protected $casts = [
        'montant_modification' => 'decimal:2',
        'date_annulation'      => 'datetime',
    ];

    /**
     * Ce que la suppression de ce mouvement emportera avec lui, pour l'annoncer avant.
     */
    public function resumeDeCeQuIlCreait(): string
    {
        $parties = [];

        if ($this->nouvelle_ligne_recette_id) {
            $parties[] = 'la ligne de recette ' . ($this->nouvelleLigneRecette?->code_nomenclature ?? 'n°' . $this->nouvelle_ligne_recette_id);
        }

        if ($this->nouvelle_ligne_depense_id) {
            $parties[] = 'la ligne de dépense ' . ($this->nouvelleLigneDepense?->nomenclature?->code ?? 'n°' . $this->nouvelle_ligne_depense_id);
        }

        if ($this->nomenclature_creee_id) {
            $parties[] = 'la nomenclature ' . (NomenclatureBudgetaire::withTrashed()->find($this->nomenclature_creee_id)?->code ?? 'n°' . $this->nomenclature_creee_id);
        }

        if ($this->virement_budgetaire_id && $this->virementBudgetaire?->statut === 'en_attente') {
            $parties[] = 'le virement encore en attente';
        }

        return implode(', ', $parties);
    }

    protected static function booted(): void
    {
        // ✅ Les mouvements sont supprimés PHYSIQUEMENT (pas de deleted_at sur cette
        //    table). Sans cette cascade, supprimer puis réajouter le même mouvement
        //    laissait la ligne créée la première fois en place : doublon de code
        //    dans le budget / la prévision, impossible à retirer une fois la
        //    prévision « adopté ».
        static::deleting(function (self $mouvement) {
            $mouvement->retirerCeQuIlCreait();
        });
    }

    /**
     * Nettoie ce que la création de ce mouvement avait inséré : ligne(s) de budget
     * ou de prévision, nomenclature de nouvelle création, virement mis en attente.
     *
     * Refuse dès qu'une de ces traces porte déjà une opération comptable : on ne
     * détruit jamais un engagement, un paiement ou un recouvrement.
     */
    public function retirerCeQuIlCreait(): void
    {
        if ($this->nouvelle_ligne_recette_id) {
            $ligne = LignePrevisionRecette::find($this->nouvelle_ligne_recette_id);

            if ($ligne) {
                // Même règle que le recouvrement : une recette « prevue » n'est pas
                // encore de l'argent, elle ne doit pas verrouiller la ligne.
                $recouvre = (float) $ligne->montant_recouvre;
                $constatees = $ligne->recettesReelles()->where('statut', '!=', 'prevue')->count();

                if ($recouvre > 0 || $constatees > 0) {
                    throw new \RuntimeException(
                        'Suppression impossible : la ligne ' . ($ligne->code_nomenclature ?? $ligne->id)
                            . ' créée par ce mouvement a encaissé '
                            . number_format($recouvre, 0, ',', ' ') . ' FCFA ('
                            . $constatees . ' recette(s) enregistrée(s)). Épursez-la d\'abord.'
                    );
                }

                $ligne->delete();   // l'observateur propage le retrait aux 12 mois
            }
        }

        if ($this->nouvelle_ligne_depense_id) {
            $ligne = LigneBudgetaire::find($this->nouvelle_ligne_depense_id);

            if ($ligne) {
                $dejaUtilisee = (float) $ligne->engage + (float) $ligne->ordonne
                    + (float) $ligne->liquide + (float) $ligne->paye;

                if ($dejaUtilisee > 0) {
                    throw new \RuntimeException(
                        'Suppression impossible : la ligne ' . ($ligne->nomenclature?->code ?? $ligne->id)
                            . ' créée par ce mouvement porte déjà des opérations ('
                            . number_format($dejaUtilisee, 0, ',', ' ') . ' FCFA engagés/ordonancés/payés).'
                    );
                }

                $ligne->delete();
            }
        }

        // Nomenclature née de ce mouvement (mode « créer une nouvelle nomenclature ») :
        // elle doit partir avec lui, sinon son code reste proposé aux sélecteurs et le
        // réajout du mouvement est refusé — ou pire, son code est recréé en doublon.
        // On ne la retire que si plus aucune ligne VIVANTE ne l'utilise.
        if ($this->nomenclature_creee_id) {
            $nomenclature = NomenclatureBudgetaire::find($this->nomenclature_creee_id);

            if ($nomenclature) {
                $referencee = LigneBudgetaire::where('nomenclature_id', $nomenclature->id)->exists()
                    || LignePrevisionRecette::where('nomenclature_id', $nomenclature->id)->exists();

                if (!$referencee) {
                    $nomenclature->delete();
                }
            }
        }

        // Virement créé en attente par ce mouvement : s'il n'a jamais été exécuté,
        // il ne doit pas survivre seul à la suppression du mouvement.
        if ($this->virement_budgetaire_id) {
            $virement = VirementBudgetaire::find($this->virement_budgetaire_id);

            if ($virement && $virement->statut === 'en_attente') {
                $virement->delete();
            }
        }

        // ✅ Même rattrapage d'agrégats que ReinitialisationCollectifsService :
        //    sans lui, budget, prévision et tableau de bord garderaient les montants
        //    de la ligne retirée.
        PrevisionRecette::find($this->previsionRecetteIdConcernee())?->recalculerTotaux();
        Budget::find($this->budgetIdConcerne())?->recalculerTotaux();
        Exercice::find($this->collectif?->exercice_id)?->mettreAJourStatistiques();
    }

    /**
     * Prévision touchée par les lignes créées de ce mouvement.
     */
    private function previsionRecetteIdConcernee(): ?int
    {
        $ligne = $this->nouvelle_ligne_recette_id
            ? LignePrevisionRecette::withTrashed()->find($this->nouvelle_ligne_recette_id)
            : null;

        if ($ligne) return (int) $ligne->prevision_recette_id;

        $autre = $this->ligne_recette_id ? LignePrevisionRecette::find($this->ligne_recette_id) : null;

        return $autre ? (int) $autre->prevision_recette_id : null;
    }

    /**
     * Budget touché par les lignes créées de ce mouvement.
     */
    private function budgetIdConcerne(): ?int
    {
        $ligne = $this->nouvelle_ligne_depense_id
            ? LigneBudgetaire::withTrashed()->find($this->nouvelle_ligne_depense_id)
            : ($this->ligne_depense_id ? LigneBudgetaire::find($this->ligne_depense_id) : null);

        return $ligne ? (int) $ligne->budget_id : null;
    }

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

    public function annulateur()
    {
        return $this->belongsTo(User::class, 'annule_par');
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
                if (in_array($virement->statut, ['en_attente', 'rejete'])) {
                    $ancienStatut = $virement->statut;
                    $virement->statut = 'approuve';
                    $virement->valide_par = $user?->id;
                    $virement->date_validation = now();
                    // ✅ Synchronise le montant du virement avec celui du mouvement
                    //    (utile si le montant a été modifié via corriger())
                    $virement->montant = $this->montant_modification;
                    $virement->save();

                    ActivityLog::logAction($virement, 'valider', [
                        'ancien_statut'  => $ancienStatut,
                        'nouveau_statut' => 'approuve',
                        'approuve_par'   => $user?->name ?? 'Système (adoption collectif)',
                        'montant'        => $virement->montant,
                        'contexte'       => $ancienStatut === 'rejete'
                            ? 'Ré-approuvé automatiquement suite à la correction d\'un mouvement collectif'
                            : 'Auto-approuvé via adoption du collectif budgétaire',
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
                    $ligne = $this->nouvelleLigneDepense;
                    if ($ligne) {
                        // ✅ Restaure explicitement les montants (symétrique à annuler())
                        //    pour que la réapplication après correction fonctionne.
                        $ligne->updateQuietly([
                            'est_issue_collectif'   => true,
                            'collectif_creation_id' => $this->collectif_budgetaire_id,
                            'budget_initial'        => $this->montant_modification,
                            'budget_rectifie'       => $this->montant_modification,
                            'montant_initial'       => $this->montant_modification,
                        ]);
                    }
                } elseif ($this->ligne_depense_id) {
                    $ligne = $this->ligneDepense;
                    if ($ligne) {
                        // ✅ Recalcul complet — budget_rectifie = initial + Σ collectifs adoptés.
                        //    Le mouvement doit être actif AVANT le recalcul pour y être compté.
                        $this->updateQuietly(['statut' => 'actif', 'date_annulation' => null, 'annule_par' => null]);
                        \App\Filament\Budget\Resources\FicheControleEngagementsResource::recalculerLigne($ligne);
                    }
                }
            } else { // recette
                if ($this->nouvelle_ligne_recette_id) {
                    $ligne = $this->nouvelleLigneRecette;
                    if ($ligne) {
                        $ligne->updateQuietly([
                            'est_issue_collectif'   => true,
                            'collectif_creation_id' => $this->collectif_budgetaire_id,
                            'montant_prevu_initial' => $this->montant_modification,
                            'montant_rectifie'      => $this->montant_modification,
                        ]);
                    }
                } elseif ($this->ligne_recette_id) {
                    $ligne = $this->ligneRecette;
                    if ($ligne) {
                        // ✅ CORRIGÉ — recalcul complet (initial + Σ mouvements adoptés), comme pour
                        //    les dépenses. L'ancien « += » doublait le montant à chaque réapplication.
                        //    Le mouvement doit être actif AVANT le recalcul pour y être compté.
                        $this->updateQuietly(['statut' => 'actif', 'date_annulation' => null, 'annule_par' => null]);
                        $ligne->recalculerRectifie();
                    }
                }
            }
        }

        // ✅ Mouvement (re)devient actif — utile pour la réapplication après correction
        $this->updateQuietly([
            'statut'          => 'actif',
            'date_annulation' => null,
            'annule_par'      => null,
        ]);
    }

    /**
     * Corriger ce mouvement : change le montant puis le réapplique.
     * Le mouvement doit d'abord avoir été annulé.
     */
    public function corriger(float $nouveauMontant, ?User $user = null, ?string $motifCorrection = null): void
    {
        if ($this->statut !== 'annule') {
            throw new \Exception(
                "Ce mouvement doit d'abord être annulé avant de pouvoir être corrigé."
            );
        }

        $this->motif = ($this->motif ?? '')
            . "\n[Correction — " . now()->format('d/m/Y H:i') . "] "
            . "Montant modifié de " . number_format((float) $this->montant_modification, 0, ',', ' ')
            . " à " . number_format($nouveauMontant, 0, ',', ' ') . " FCFA"
            . ($motifCorrection ? " — {$motifCorrection}" : '');
        $this->montant_modification = $nouveauMontant;
        $this->save();

        $this->appliquer($user);
    }

    /**
     * Annuler ce mouvement (et lui seul — les autres mouvements du même
     * collectif restent inchangés).
     */
    public function annuler(?User $user = null): void
    {
        if ($this->statut === 'annule') {
            throw new \Exception("Ce mouvement est déjà annulé.");
        }

        // ✅ Garde — vérifier que l'annulation ne rend pas le disponible négatif
        if ($this->type === 'depense' && $this->ligne_depense_id && $this->montant_modification > 0) {
            $ligne = $this->ligneDepense;
            if ($ligne) {
                // Après annulation, budget_rectifie sera réduit du montant du mouvement.
                // getBudgetRectifieReel() plutôt que la colonne stockée : l'analyse de
                // réinitialisation utilise la même formule, elles ne peuvent plus diverger.
                $futureRectifie = $ligne->getBudgetRectifieReel() - (float) $this->montant_modification;
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

            $this->updateQuietly([
                'statut'          => 'annule',
                'date_annulation' => now(),
                'annule_par'      => $user?->id,
            ]);
            return;
        }

        if ($this->type === 'depense') {
            if ($this->nouvelle_ligne_depense_id) {
                $ligne = $this->nouvelleLigneDepense;
                if ($ligne) {
                    // ✅ Garde — cette ligne n'existe QUE grâce à ce collectif ;
                    //    si elle a déjà des engagements, on ne peut pas
                    //    l'annuler sans perdre la traçabilité comptable.
                    if ((float) $ligne->engage > 0) {
                        throw new \Exception(
                            "Impossible d'annuler : la ligne "
                                . ($ligne->nomenclature?->code ?? $ligne->id)
                                . " créée par ce collectif a déjà des engagements ("
                                . number_format($ligne->engage, 0, ',', ' ')
                                . " FCFA). Désengagez d'abord."
                        );
                    }

                    // ✅ Remise à zéro complète — sans ce collectif, cette ligne
                    //    ne doit plus peser sur le budget total (sinon elle
                    //    continue de fausser les statistiques du tableau de bord
                    //    même après annulation).
                    $ligne->updateQuietly([
                        'est_issue_collectif'   => false,
                        'collectif_creation_id' => null,
                        'budget_initial'        => 0,
                        'budget_rectifie'       => 0,
                        'montant_initial'       => 0,
                        'disponible_engagement' => 0,
                    ]);
                }
            } elseif ($this->ligne_depense_id) {
                $ligne = $this->ligneDepense;
                if ($ligne) {
                    // ✅ Le mouvement est marqué annulé AVANT le recalcul, qui l'exclut
                    //    alors du total. Symétrique de la branche « recette » ci-dessous.
                    $this->updateQuietly(['statut' => 'annule', 'date_annulation' => now(), 'annule_par' => $user?->id]);
                    \App\Filament\Budget\Resources\FicheControleEngagementsResource::recalculerLigne($ligne);
                }
            }
        } else {
            if ($this->nouvelle_ligne_recette_id) {
                $ligne = $this->nouvelleLigneRecette;
                if ($ligne) {
                    // ✅ Même garde que pour les dépenses, côté recouvrement
                    if ((float) $ligne->montant_recouvre > 0) {
                        throw new \Exception(
                            "Impossible d'annuler : la ligne "
                                . ($ligne->nomenclature?->code ?? $ligne->id)
                                . " créée par ce collectif a déjà du recouvrement enregistré ("
                                . number_format($ligne->montant_recouvre, 0, ',', ' ')
                                . " FCFA)."
                        );
                    }

                    $ligne->updateQuietly([
                        'est_issue_collectif'    => false,
                        'collectif_creation_id'  => null,
                        'montant_prevu_initial'  => 0,
                        'montant_rectifie'       => 0,
                    ]);
                }
            } elseif ($this->ligne_recette_id) {
                $ligne = $this->ligneRecette;
                if ($ligne) {
                    // ✅ CORRIGÉ — le mouvement est marqué annulé AVANT le recalcul, qui l'exclut
                    //    alors du total (au lieu de soustraire un montant d'un cumul peut-être faux).
                    $this->updateQuietly(['statut' => 'annule', 'date_annulation' => now(), 'annule_par' => $user?->id]);
                    $ligne->recalculerRectifie();
                }
            }
        }

        $this->updateQuietly([
            'statut'          => 'annule',
            'date_annulation' => now(),
            'annule_par'      => $user?->id,
        ]);
    }
}
