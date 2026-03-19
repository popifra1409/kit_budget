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
        // Vérifier les permissions
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        // Préparer les données (même logique que le PDF)
        $ligneBudgetaire = \App\Models\LigneBudgetaire::with([
            'exercice',
            'budget',
            'nomenclature.programme',
            'nomenclature.action',
            'nomenclature.activite',
            'nomenclature.tache',
            'nomenclature.article',
            'nomenclature.paragraphe',
            'engagements' => function ($query) {
                $query->with('engageable')->orderBy('date_engagement', 'asc');
            }
        ])->findOrFail($ligneBudgetaireId);

        $data = $this->preparerDonnees($ligneBudgetaire);

        // Retourner la vue HTML (sans générer le PDF)
        return view('pdf.fiche-controle-engagements', $data);
    }

    /**
     * Télécharger le PDF de la fiche de contrôle
     */
    public function telechargerPdf(int $ligneBudgetaireId)
    {
        // Vérifier les permissions
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        // Générer le PDF
        $pdf = $this->pdfService->genererPdf($ligneBudgetaireId);

        // Récupérer la nomenclature pour le nom du fichier
        $ligneBudgetaire = \App\Models\LigneBudgetaire::with('nomenclature')
            ->findOrFail($ligneBudgetaireId);

        $filename = "fiche_controle_engagements_{$ligneBudgetaire->nomenclature->code}_"
            . now()->format('Ymd') . ".pdf";

        // Télécharger
        return $pdf->download($filename);
    }

    /**
     * Afficher le PDF dans le navigateur
     */
    public function afficherPdf(int $ligneBudgetaireId)
    {
        // Vérifier les permissions
        if (!auth()->user()?->can('view_fiche_controle_engagements')) {
            abort(403, 'Accès non autorisé');
        }

        // Générer le PDF
        $pdf = $this->pdfService->genererPdf($ligneBudgetaireId);

        // Afficher dans le navigateur
        return $pdf->stream();
    }

    /**
     * Préparer les données (copié du service pour le preview HTML)
     */
    protected function preparerDonnees(\App\Models\LigneBudgetaire $ligneBudgetaire): array
    {
        $engagements = $ligneBudgetaire->engagements;

        // Calculer les totaux
        $dotationInitiale = $ligneBudgetaire->dotation_initiale;
        $totalEngage = $engagements->sum('montant_engage');
        $disponible = $ligneBudgetaire->disponible_engagement;
        $tauxConsommation = $dotationInitiale > 0 ? ($totalEngage / $dotationInitiale) * 100 : 0;

        // Préparer les lignes d'engagements
        $lignesEngagements = [];
        $disponibleProgressif = $dotationInitiale;

        foreach ($engagements as $engagement) {
            $engageable = $engagement->engageable;

            $disponibleProgressif -= $engagement->montant_engage;

            $lignesEngagements[] = [
                'beneficiaire' => $this->getBeneficiaire($engageable),
                'objet' => $this->getObjet($engageable),
                'reference' => $this->getReference($engageable),
                'date_engagement' => $engagement->date_engagement ? $engagement->date_engagement->format('d/m/Y') : '-',
                'montant_engage' => $engagement->montant_engage,
                'disponible_apres' => $disponibleProgressif,
                'statut' => $engagement->statut ?? 'valide',
                'observations' => $this->getObservations($engageable),
            ];
        }

        // Hiérarchie budgétaire
        $nomenclature = $ligneBudgetaire->nomenclature;
        $hierarchie = [
            'programme' => $nomenclature->programme?->code . ' - ' . $nomenclature->programme?->libelle,
            'action' => $nomenclature->action?->code . ' - ' . $nomenclature->action?->libelle,
            'activite' => $nomenclature->activite?->code . ' - ' . $nomenclature->activite?->libelle,
            'tache' => $nomenclature->tache ? $nomenclature->tache->code . ' - ' . $nomenclature->tache->libelle : null,
            'article' => $nomenclature->article?->code . ' - ' . $nomenclature->article?->libelle,
            'paragraphe' => $nomenclature->paragraphe?->code . ' - ' . $nomenclature->paragraphe?->libelle,
        ];

        return [
            'ligneBudgetaire' => $ligneBudgetaire,
            'exercice' => $ligneBudgetaire->exercice,
            'budget' => $ligneBudgetaire->budget,
            'nomenclature' => $nomenclature,
            'hierarchie' => $hierarchie,
            'engagements' => $lignesEngagements,
            'dotation_initiale' => $dotationInitiale,
            'total_engage' => $totalEngage,
            'disponible' => $disponible,
            'taux_consommation' => $tauxConsommation,
            'date_generation' => now()->format('d/m/Y à H:i'),
            'generePar' => auth()->user()?->name ?? 'Système',
        ];
    }

    protected function getBeneficiaire($engageable): string
    {
        if (!$engageable) return '-';
        if (method_exists($engageable, 'fournisseur') && $engageable->fournisseur) {
            return $engageable->fournisseur->raison_sociale;
        }
        if (method_exists($engageable, 'beneficiaire') && $engageable->beneficiaire) {
            return $engageable->beneficiaire->nom_complet ?? $engageable->beneficiaire->name;
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
}
