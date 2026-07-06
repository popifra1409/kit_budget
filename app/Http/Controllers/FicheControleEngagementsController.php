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
                'beneficiairePersonnel',
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
     */
    protected function preparerDonnees(\App\Models\LigneBudgetaire $ligneBudgetaire, $engagements): array
    {
        $dotationInitiale  = $ligneBudgetaire->budget_initial     ?? 0;
        $virementsEntrants = $ligneBudgetaire->virements_entrants ?? 0;
        $virementsSortants = $ligneBudgetaire->virements_sortants ?? 0;
        $budgetRectifie    = $ligneBudgetaire->budget_rectifie
            ?? ($dotationInitiale + $virementsEntrants - $virementsSortants);

        // Filtrer les DA prévisionnelles AVANT tous les calculs
        $engagements = $engagements->filter(function ($engagement) {
            $engageable = $engagement->engageable;
            return !($engageable instanceof \App\Models\DecisionAdministrative
                && $engageable->est_previsionnel);
        });

        $totalEngage = $engagements->sum('montant_engage');

        // ✅ FIX — disponible calculé dynamiquement (colonne stockée peut être stale)
        $disponible = $budgetRectifie - $totalEngage;

        $tauxConsommation = $budgetRectifie > 0
            ? ($totalEngage / $budgetRectifie) * 100 : 0;

        $lignesEngagements    = [];
        $disponibleProgressif = $budgetRectifie;
        $totalOp              = 0;
        $totalOpt             = 0;

        // ✅ Tri par date pour que disponible_apres soit cohérent
        foreach ($engagements->sortBy('date_engagement') as $engagement) {
            $engageable = $engagement->engageable;

            $disponibleProgressif -= $engagement->montant_engage;

            $montantOp  = $this->getMontantOp($engagement);
            $montantOpt = $this->getMontantOpt($engagement);
            $totalOp   += $montantOp;
            $totalOpt  += $montantOpt;

            $lignesEngagements[] = [
                'numero_engagement' => $engagement->numero ?? $engagement->id ?? '-',
                'beneficiaire'      => $this->getBeneficiaire($engagement),
                'objet'             => $this->getObjet($engageable),
                'reference'         => $this->getReference($engageable),
                'date_engagement'   => $engagement->date_engagement
                    ? $engagement->date_engagement->format('d/m/Y') : '-',
                'montant_engage'    => $engagement->montant_engage,
                'disponible_apres'  => $disponibleProgressif,
                'numero_op'         => $this->getNumeroOp($engagement),
                'montant_op'        => $montantOp,
                'montant_opt'       => $montantOpt,
                'statut'            => $engagement->statut ?? 'valide',
                'observations'      => $this->getObservations($engageable),
            ];
        }

        $nomenclature = $ligneBudgetaire->nomenclature;
        $hierarchie   = $this->recupererHierarchie($nomenclature);

        return [
            'ligneBudgetaire'     => $ligneBudgetaire,
            'exercice'            => $ligneBudgetaire->budget?->exercice,
            'budget'              => $ligneBudgetaire->budget,
            'nomenclature'        => $nomenclature,
            'hierarchie'          => $hierarchie,
            'engagements'         => $lignesEngagements,
            'dotation_initiale'   => $dotationInitiale,
            'virements_entrants'  => $virementsEntrants,
            'virements_sortants'  => $virementsSortants,
            'budget_rectifie'     => $budgetRectifie,
            'total_engage'        => $totalEngage,
            'disponible'          => $disponible,
            'taux_consommation'   => $tauxConsommation,
            'total_op'            => $totalOp,
            'total_opt'           => $totalOpt,
            'date_generation'     => now()->format('d/m/Y à H:i'),
            'generePar'           => auth()->user()?->name ?? 'Système',
            'gestionnaireCredits' => $this->getGestionnaireCredits(),
            'logo'                => $this->getLogo(),
            'nomStructure'        => $this->getNomStructure(),
            'sousDirection'       => $this->getSousDirection(),
            'fonctionOrdonnateur' => $this->getFonctionOrdonnateur(),
        ];
    }

    // =========================================================
    // ✅ CORRIGÉS POUR LES AVENANTS
    // =========================================================

    /**
     * ✅ FIX — liste TOUS les numéros d'OP standard actives
     *
     * AVANT : ->ordonnancesPaiement()->first() → 1 requête SQL par engagement
     *         + rate les avenants (2ème OP non prise en compte)
     *
     * APRÈS : ->ordonnancesPaiement (collection chargée) → 0 requête SQL
     *         + retourne tous les numéros séparés par ' / '
     */
    protected function getNumeroOp($engagement): string
    {
        if (!$engagement) return '-';

        $numeros = $engagement->ordonnancesPaiement
            ->where('type_ordonnance', 'standard')
            ->whereNotIn('statut', ['annulee'])
            ->pluck('numero')
            ->filter()
            ->join(' / ');

        return $numeros ?: '-';
    }

    /**
     * ✅ FIX — somme de TOUTES les OPs standard actives
     *
     * AVANT : ->ordonnancesPaiement()->where(...)->first()->montant_net
     *         → 1 requête SQL par engagement
     *         → rate les avenants qui créent une nouvelle OP ou modifient montant_net
     *
     * APRÈS : ->ordonnancesPaiement (collection chargée) → 0 requête SQL
     *         → sum() sur toutes les OPs standard non annulées
     */
    protected function getMontantOp($engagement): float
    {
        if (!$engagement) return 0;

        return (float) $engagement->ordonnancesPaiement
            ->where('type_ordonnance', 'standard')
            ->whereNotIn('statut', ['annulee'])
            ->sum('montant_net');
    }

    /**
     * ✅ FIX — somme de TOUTES les OPTs impôt actives
     *
     * AVANT : ->ordonnancesPaiement()->where(...)->first()->montant_net
     *         → 1 requête SQL par engagement + rate les avenants
     *
     * APRÈS : ->ordonnancesPaiement (collection chargée) → 0 requête SQL
     */
    protected function getMontantOpt($engagement): float
    {
        if (!$engagement) return 0;

        return (float) $engagement->ordonnancesPaiement
            ->where('type_ordonnance', 'impot')
            ->whereNotIn('statut', ['annulee'])
            ->sum('montant_net');
    }

    // =========================================================
    // INCHANGÉS
    // =========================================================

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

    protected function getFonctionOrdonnateur(): string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->fonction_ordonnateur ?? 'Non défini';
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération fonction ordonnateur: ' . $e->getMessage());
            return 'Non défini';
        }
    }

    protected function getLogo(): ?string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->logo ?? 'null';
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération logo: ' . $e->getMessage());
            return null;
        }
    }

    protected function getNomStructure(): string
    {
        try {
            $parametre = \App\Models\ParametresStructure::first();
            return $parametre?->nom_structure ?? 'HÔPITAL';
        } catch (\Exception $e) {
            return 'HÔPITAL';
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

    protected function recupererHierarchie($nomenclature): array
    {
        $hierarchie = [];

        if (!$nomenclature) return $hierarchie;

        try {
            $tache = \App\Models\Tache::where('nomenclature_id', $nomenclature->id)->first();

            if ($tache) {
                $activite  = $tache->activite;
                $action    = $activite?->action;
                $programme = $action?->programme;

                if ($programme) $hierarchie['programme'] = $programme->code . ' - ' . $programme->libelle;
                if ($action)    $hierarchie['action']    = $action->code    . ' - ' . $action->libelle;
                if ($activite)  $hierarchie['activite']  = $activite->code  . ' - ' . $activite->libelle;
                if ($tache)     $hierarchie['tache']     = $tache->code     . ' - ' . $tache->libelle;
            }

            if (method_exists($nomenclature, 'getArticle')) {
                $article = $nomenclature->getArticle();
                if ($article) $hierarchie['article'] = $article->code . ' - ' . $article->libelle;
            } else {
                if (method_exists($nomenclature, 'getCodeArticle')) {
                    $codeArticle = $nomenclature->getCodeArticle();
                    $hierarchie['article'] = $codeArticle;
                }
            }

            if (method_exists($nomenclature, 'getParagraphe')) {
                $paragraphe = $nomenclature->getParagraphe();
                if ($paragraphe) $hierarchie['paragraphe'] = $paragraphe->code . ' - ' . $paragraphe->libelle;
            } elseif ($nomenclature->parent && in_array(
                $nomenclature->parent->niveau ?? $nomenclature->parent->niveau_hierarchique ?? '',
                ['paragraphe']
            )) {
                $paragraphe = $nomenclature->parent;
                $hierarchie['paragraphe'] = $paragraphe->code . ' - ' . $paragraphe->libelle;
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération hiérarchie: ' . $e->getMessage());
        }

        return $hierarchie;
    }

    protected function getBeneficiaire($engagement): string
    {
        if (!$engagement) return '-';

        if ($engagement->beneficiaire_id && $engagement->beneficiaire_type) {
            try {
                $beneficiaire = $engagement->beneficiaire;
                if ($beneficiaire instanceof \App\Models\Fournisseur) return $beneficiaire->raison_sociale ?? '-';
                if ($beneficiaire instanceof \App\Models\Personnel)  return $beneficiaire->nom_complet ?? $beneficiaire->nom ?? '-';
            } catch (\Exception $e) {
                \Log::warning('Erreur chargement beneficiaire polymorphique: ' . $e->getMessage());
            }
        }

        try {
            if ($engagement->beneficiaire_fournisseur_id && $engagement->beneficiaireFournisseur)
                return $engagement->beneficiaireFournisseur->raison_sociale ?? '-';
            if ($engagement->beneficiaire_personnel_id && $engagement->beneficiairePersonnel)
                return $engagement->beneficiairePersonnel->nom_complet ?? $engagement->beneficiairePersonnel->nom ?? '-';
        } catch (\Exception $e) {
            \Log::warning('Erreur chargement beneficiaire colonnes: ' . $e->getMessage());
        }

        try {
            $engageable = $engagement->engageable;
            if (!$engageable) return '-';

            if ($engageable instanceof \App\Models\BonCommande) {
                $fournisseur = $engageable->fournisseur;
                if ($fournisseur) return $fournisseur->raison_sociale ?? '-';
            }

            if ($engageable instanceof \App\Models\DecisionAdministrative) {
                if (isset($engageable->personnel) && $engageable->personnel)
                    return $engageable->personnel->nom_complet ?? $engageable->personnel->nom ?? '-';
                if (isset($engageable->fournisseur) && $engageable->fournisseur)
                    return $engageable->fournisseur->raison_sociale ?? '-';
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
        if (isset($engageable->statut) && in_array($engageable->statut, ['annule', 'rejete']))
            $observations[] = strtoupper($engageable->statut);
        if (isset($engageable->observations) && !empty($engageable->observations))
            $observations[] = substr($engageable->observations, 0, 50);
        return implode(' | ', $observations);
    }
}
