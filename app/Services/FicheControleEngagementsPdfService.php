<?php

namespace App\Services;

use App\Models\LigneBudgetaire;
use Barryvdh\DomPDF\Facade\Pdf;

class FicheControleEngagementsPdfService
{
    /**
     * Générer le PDF de la fiche de contrôle
     */
    public function genererPdf(int $ligneBudgetaireId)
    {
        // ✅ Charger les relations (SANS 'exercice')
        $ligneBudgetaire = LigneBudgetaire::with([
            'budget.exercice',  // ✅ Via budget
            'budget',
            'nomenclature',
        ])->findOrFail($ligneBudgetaireId);

        // ✅ Charger les engagements avec leurs OP et bénéficiaires
        // On charge juste l'engageable, les sous-relations seront chargées à la demande
        $engagements = $ligneBudgetaire->engagements()
            ->with([
                'engageable',
                'ordonnancesPaiement',
                'beneficiaire',
                'beneficiaireFournisseur',
                'beneficiairePersonnel'
            ])
            ->get();

        // Préparer les données
        $data = $this->preparerDonnees($ligneBudgetaire, $engagements);

        // Générer le PDF
        $pdf = Pdf::loadView('pdf.fiche-controle-engagements', $data);

        // Configuration du PDF - ✅ FORMAT PAYSAGE (LANDSCAPE)
        $pdf->setPaper('A4', 'landscape');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf;
    }

    /**
     * Préparer les données pour le PDF
     */
    protected function preparerDonnees(LigneBudgetaire $ligneBudgetaire, $engagements): array
    {
        // Calculer les totaux
        $dotationInitiale = $ligneBudgetaire->budget_initial ?? 0;
        $virementsEntrants = $ligneBudgetaire->virements_entrants ?? 0;
        $virementsSortants = $ligneBudgetaire->virements_sortants ?? 0;
        $budgetRectifie = $ligneBudgetaire->budget_rectifie ?? ($dotationInitiale + $virementsEntrants - $virementsSortants);
        $totalEngage = $engagements->sum('montant_engage');
        $disponible = $ligneBudgetaire->disponible_engagement ?? ($budgetRectifie - $totalEngage);
        $tauxConsommation = $budgetRectifie > 0 ? ($totalEngage / $budgetRectifie) * 100 : 0;
        // Préparer les lignes d'engagements
        $lignesEngagements = [];
        $disponibleProgressif = $dotationInitiale;
        $totalOp = 0;
        $totalOpt = 0;

        foreach ($engagements as $engagement) {
            $engageable = $engagement->engageable;
            $disponibleProgressif -= $engagement->montant_engage;

            // Récupérer les montants OP et OPT
            $montantOp = $this->getMontantOp($engagement);
            $montantOpt = $this->getMontantOpt($engagement);

            $totalOp += $montantOp;
            $totalOpt += $montantOpt;

            $lignesEngagements[] = [
                'numero_engagement' => $engagement->numero_engagement ?? $engagement->id ?? '-',
                'beneficiaire' => $this->getBeneficiaire($engagement),
                'objet' => $this->getObjet($engageable),
                'reference' => $this->getReference($engageable),
                'date_engagement' => $engagement->date_engagement ? $engagement->date_engagement->format('d/m/Y') : '-',
                'montant_engage' => $engagement->montant_engage,
                'disponible_apres' => $disponibleProgressif,
                'numero_op' => $this->getNumeroOp($engagement),
                'montant_op' => $montantOp,
                'montant_opt' => $montantOpt,
                'statut' => $engagement->statut ?? 'valide',
                'observations' => $this->getObservations($engageable),
            ];
        }

        // ✅ Récupérer la hiérarchie via Tache
        $nomenclature = $ligneBudgetaire->nomenclature;
        $hierarchie = $this->recupererHierarchie($nomenclature);

        return [
            'ligneBudgetaire' => $ligneBudgetaire,
            'exercice' => $ligneBudgetaire->budget?->exercice,
            'budget' => $ligneBudgetaire->budget,
            'nomenclature' => $nomenclature,
            'hierarchie' => $hierarchie,
            'engagements' => $lignesEngagements,
            'dotation_initiale' => $dotationInitiale,
            'virements_entrants' => $virementsEntrants,
            'virements_sortants' => $virementsSortants,
            'budget_rectifie' => $budgetRectifie,
            'total_engage' => $totalEngage,
            'disponible' => $disponible,
            'taux_consommation' => $tauxConsommation,
            'total_op' => $totalOp,
            'total_opt' => $totalOpt,
            'date_generation' => now()->format('d/m/Y à H:i'),
            'generePar' => auth()->user()?->name ?? 'Système',
            'gestionnaireCredits' => $this->getGestionnaireCredits(),
            'logo' => $this->getLogo(),
            'nomStructure' => $this->getNomStructure(),
            'sousDirection' => $this->getSousDirection(),
        ];
    }

    /**
     * ✅ Récupérer le nom du gestionnaire de crédits
     */
    protected function getGestionnaireCredits(): string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->nom_ordonnateur ?? 'Non défini';
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération gestionnaire crédits: ' . $e->getMessage());
            return 'Non défini';
        }
    }

    /**
     * ✅ Récupérer le nom de la structure
     */
    protected function getNomStructure(): string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->nom_structure ?? 'STRUCTURE';
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération nom structure: ' . $e->getMessage());
            return 'STRUCTURE';
        }
    }
    protected function getSousDirection(): string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->sous_direction ?? 'DAAF';
        } catch (\Exception $e) {
            return 'DAAF';
        }
    }

    /**
     * ✅ Récupérer le logo
     */
    protected function getLogo(): ?string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->logo ?? null;
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération logo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer la hiérarchie via Tache
     */
    protected function recupererHierarchie($nomenclature): array
    {
        $hierarchie = [];

        if (!$nomenclature) {
            return $hierarchie;
        }

        try {
            $tache = \App\Models\Tache::where('nomenclature_id', $nomenclature->id)->first();

            if ($tache) {
                $activite = $tache->activite;
                $action = $activite?->action;
                $programme = $action?->programme;

                if ($programme) {
                    $hierarchie['programme'] = $programme->code . ' - ' . $programme->libelle;
                }
                if ($action) {
                    $hierarchie['action'] = $action->code . ' - ' . $action->libelle;
                }
                if ($activite) {
                    $hierarchie['activite'] = $activite->code . ' - ' . $activite->libelle;
                }
                if ($tache) {
                    $hierarchie['tache'] = $tache->code . ' - ' . $tache->libelle;
                }
            }

            // ✅ RÉCUPÉRER ARTICLE ET PARAGRAPHE via les méthodes du modèle
            if (method_exists($nomenclature, 'getArticle')) {
                $article = $nomenclature->getArticle();
                if ($article) {
                    $hierarchie['article'] = $article->code . ' - ' . $article->libelle;
                }
            } else {
                // Fallback : utiliser getCodeArticle()
                if (method_exists($nomenclature, 'getCodeArticle')) {
                    $codeArticle = $nomenclature->getCodeArticle();
                    $hierarchie['article'] = $codeArticle;
                    // if ($codeArticle) {
                    //     $article = \App\Models\NomenclatureBudgetaire::where('code', $codeArticle)
                    //         ->where('niveau_hierarchique', 'article')
                    //         ->orWhere('niveau', 'article')
                    //         ->first();

                    //     if ($article) {
                    //         $hierarchie['article'] = $article->code . ' - ' . $article->libelle;
                    //     }
                    // }
                }
            }

            // Paragraphe
            if (method_exists($nomenclature, 'getParagraphe')) {
                $paragraphe = $nomenclature->getParagraphe();
                if ($paragraphe) {
                    $hierarchie['paragraphe'] = $paragraphe->code . ' - ' . $paragraphe->libelle;
                }
            } elseif ($nomenclature->parent && in_array($nomenclature->parent->niveau ?? $nomenclature->parent->niveau_hierarchique ?? '', ['paragraphe'])) {
                $paragraphe = $nomenclature->parent;
                $hierarchie['paragraphe'] = $paragraphe->code . ' - ' . $paragraphe->libelle;
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération hiérarchie PDF: ' . $e->getMessage());
        }

        return $hierarchie;
    }

    protected function getBeneficiaire($engagement): string
    {
        if (!$engagement) return '-';

        // ✅ MÉTHODE 1 : Via la relation polymorphique beneficiaire de l'engagement
        if ($engagement->beneficiaire_id && $engagement->beneficiaire_type) {
            try {
                $beneficiaire = $engagement->beneficiaire;

                if ($beneficiaire instanceof \App\Models\Fournisseur) {
                    return $beneficiaire->raison_sociale ?? '-';
                }

                if ($beneficiaire instanceof \App\Models\Personnel) {
                    return $beneficiaire->nom_complet ?? $beneficiaire->nom ?? '-';
                }
            } catch (\Exception $e) {
                \Log::warning('Erreur chargement beneficiaire polymorphique: ' . $e->getMessage());
            }
        }

        // ✅ MÉTHODE 2 : Via beneficiaireFournisseur ou beneficiairePersonnel
        try {
            if ($engagement->beneficiaire_fournisseur_id && $engagement->beneficiaireFournisseur) {
                return $engagement->beneficiaireFournisseur->raison_sociale ?? '-';
            }

            if ($engagement->beneficiaire_personnel_id && $engagement->beneficiairePersonnel) {
                return $engagement->beneficiairePersonnel->nom_complet ?? $engagement->beneficiairePersonnel->nom ?? '-';
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur chargement beneficiaire colonnes: ' . $e->getMessage());
        }

        // ✅ MÉTHODE 3 : Via l'engageable
        try {
            $engageable = $engagement->engageable;

            if (!$engageable) {
                return '-';
            }

            // Pour BC : via fournisseur
            if ($engageable instanceof \App\Models\BonCommande) {
                $fournisseur = $engageable->fournisseur;
                if ($fournisseur) {
                    return $fournisseur->raison_sociale ?? '-';
                }
            }

            // Pour DA : via personnel ou fournisseur
            if ($engageable instanceof \App\Models\DecisionAdministrative) {
                // D'abord essayer personnel
                if (isset($engageable->personnel) && $engageable->personnel) {
                    return $engageable->personnel->nom_complet ?? $engageable->personnel->nom ?? '-';
                }

                // Ensuite essayer fournisseur
                if (isset($engageable->fournisseur) && $engageable->fournisseur) {
                    return $engageable->fournisseur->raison_sociale ?? '-';
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur chargement via engageable: ' . $e->getMessage());
        }

        return '-';
    }

    protected function getObjet($engageable): string
    {
        if (!$engageable) return '-';
        return $engageable->objet ?? '-';
    }

    protected function getReference($engageable): string
    {
        if (!$engageable) return '-';
        return $engageable->numero ?? '-';
    }

    protected function getObservations($engageable): string
    {
        if (!$engageable) return '';
        $observations = [];
        if (isset($engageable->statut) && in_array($engageable->statut, ['annule', 'rejete'])) {
            $observations[] = strtoupper($engageable->statut);
        }
        if (isset($engageable->observations) && !empty($engageable->observations)) {
            $observations[] = substr($engageable->observations, 0, 50);
        }
        return implode(' | ', $observations);
    }

    /**
     * Récupérer le numéro d'OP lié à l'engagement
     */
    protected function getNumeroOp($engagement): string
    {
        if (!$engagement) return '-';

        // ✅ Récupérer l'OP de type 'standard' (bénéficiaire principal)
        $opStandard = $engagement->ordonnancesPaiement()
            ->where('type_ordonnance', 'standard')
            ->first();

        if ($opStandard) {
            return $opStandard->numero ?? '-';
        }

        // ✅ Si pas d'OP standard, prendre la première OP
        $premiereOp = $engagement->ordonnancesPaiement()->first();

        return $premiereOp?->numero ?? '-';
    }

    /**
     * Récupérer le montant d'OP lié à l'engagement
     */
    protected function getMontantOp($engagement): float
    {
        if (!$engagement) return 0;

        // ✅ Récupérer l'OP de type 'standard'
        $opStandard = $engagement->ordonnancesPaiement()
            ->where('type_ordonnance', 'standard')
            ->first();

        if ($opStandard) {
            return $opStandard->montant_net ?? 0;
        }

        // ✅ Si pas d'OP standard, prendre la première OP
        $premiereOp = $engagement->ordonnancesPaiement()->first();

        return $premiereOp?->montant_net ?? 0;
    }

    /**
     * Récupérer le montant d'OPT (Impôt) lié à l'engagement
     */
    protected function getMontantOpt($engagement): float
    {
        if (!$engagement) return 0;

        // ✅ Récupérer l'OP de type 'impot'
        $opImpot = $engagement->ordonnancesPaiement()
            ->where('type_ordonnance', 'impot')
            ->first();

        return $opImpot?->montant_net ?? 0;
    }
}
