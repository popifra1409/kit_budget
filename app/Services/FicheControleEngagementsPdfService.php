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

        // ✅ Charger les engagements séparément
        $engagements = $ligneBudgetaire->engagements()->with('engageable')->get();

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
        $dotationInitiale = $ligneBudgetaire->dotation_initiale
            ?? $ligneBudgetaire->budget_initial
            ?? 0;
        $totalEngage = $engagements->sum('montant_engage');
        $disponible = $ligneBudgetaire->disponible_engagement ?? ($dotationInitiale - $totalEngage);
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
            'total_engage' => $totalEngage,
            'disponible' => $disponible,
            'taux_consommation' => $tauxConsommation,
            'date_generation' => now()->format('d/m/Y à H:i'),
            'generePar' => auth()->user()?->name ?? 'Système',
        ];
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
        } catch (\Exception $e) {
            \Log::warning('Erreur récupération hiérarchie PDF: ' . $e->getMessage());
        }

        return $hierarchie;
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
