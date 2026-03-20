<?php

namespace App\Http\Controllers;

use App\Services\FicheControleEngagementsPdfService;
use Illuminate\Http\Request;

class FicheControleEngagementsController extends Controller
{
    protected $pdfService;

    public function __construct(FicheControleEngagementsPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Aperçu HTML de la fiche de contrôle
     */
    public function preview(int $ligneBudgetaireId)
    {
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        // ✅ Charger les relations essentielles
        $ligneBudgetaire = \App\Models\LigneBudgetaire::with([
            'budget.exercice',
            'budget',
            'nomenclature',
        ])->findOrFail($ligneBudgetaireId);

        $engagements = $ligneBudgetaire->engagements()
            ->with([
                'engageable',
                'ordonnancesPaiement',
                'beneficiaire',
                'beneficiaireFournisseur',
                'beneficiairePersonnel'
            ])
            ->get();

        $data = $this->preparerDonnees($ligneBudgetaire, $engagements);

        return view('pdf.fiche-controle-engagements', $data);
    }

    /**
     * Télécharger le PDF
     */
    public function telechargerPdf(int $ligneBudgetaireId)
    {
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        $pdf = $this->pdfService->genererPdf($ligneBudgetaireId);

        $ligneBudgetaire = \App\Models\LigneBudgetaire::with('nomenclature')
            ->findOrFail($ligneBudgetaireId);

        $filename = "fiche_controle_engagements_{$ligneBudgetaire->nomenclature->code}_"
            . now()->format('Ymd') . ".pdf";

        return $pdf->download($filename);
    }

    /**
     * Afficher le PDF
     */
    public function afficherPdf(int $ligneBudgetaireId)
    {
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        $pdf = $this->pdfService->genererPdf($ligneBudgetaireId);

        return $pdf->stream();
    }

    /**
     * Préparer les données
     * ✅ CORRIGÉ : Récupère la hiérarchie via Tache + Colonnes OP/OPT
     */
    protected function preparerDonnees(\App\Models\LigneBudgetaire $ligneBudgetaire, $engagements): array
    {
        // Calculer les totaux
        $dotationInitiale = $ligneBudgetaire->dotation_initiale ?? $ligneBudgetaire->budget_initial ?? 0;
        $totalEngage = $engagements->sum('montant_engage');
        $disponible = $ligneBudgetaire->disponible_engagement ?? ($dotationInitiale - $totalEngage);
        $tauxConsommation = $dotationInitiale > 0 ? ($totalEngage / $dotationInitiale) * 100 : 0;

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
                'numero_engagement' => $engagement->numero ?? $engagement->id ?? '-',
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

        // ✅ RÉCUPÉRER LA HIÉRARCHIE VIA TACHE
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
            'total_engage' => $totalEngage,
            'disponible' => $disponible,
            'taux_consommation' => $tauxConsommation,
            'total_op' => $totalOp,
            'total_opt' => $totalOpt,
            'date_generation' => now()->format('d/m/Y à H:i'),
            'generePar' => auth()->user()?->name ?? 'Système',
            'gestionnaireCredits' => $this->getGestionnaireCredits(),
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
     * ✅ NOUVELLE MÉTHODE : Récupérer la hiérarchie via Tache
     */
    protected function recupererHierarchie($nomenclature): array
    {
        $hierarchie = [];

        if (!$nomenclature) {
            return $hierarchie;
        }

        try {
            // Chercher la tache liée à cette nomenclature
            $tache = \App\Models\Tache::where('nomenclature_id', $nomenclature->id)->first();

            if ($tache) {
                // Récupérer la hiérarchie via Tache
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

            // Ajouter les autres niveaux si disponibles
            // Note: Article et Paragraphe ne sont pas dans Tache, 
            // ils pourraient être dans NomenclatureBudgetaire directement
            if (method_exists($nomenclature, 'article') && $nomenclature->article) {
                $hierarchie['article'] = $nomenclature->article->code . ' - ' . $nomenclature->article->libelle;
            }
            if (method_exists($nomenclature, 'paragraphe') && $nomenclature->paragraphe) {
                $hierarchie['paragraphe'] = $nomenclature->paragraphe->code . ' - ' . $nomenclature->paragraphe->libelle;
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs, retourner hierarchie vide
            \Log::warning('Erreur récupération hiérarchie: ' . $e->getMessage());
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
                    return $beneficiaire->nom_complet ?? $beneficiaire->name ?? '-';
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
                return $engagement->beneficiairePersonnel->nom_complet ?? $engagement->beneficiairePersonnel->name ?? '-';
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
                    return $engageable->personnel->nom_complet ?? $engageable->personnel->name ?? '-';
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
