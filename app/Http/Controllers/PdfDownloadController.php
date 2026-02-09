<?php

namespace App\Http\Controllers;

use App\Services\PdfGenerator\PdfGenerator;
use App\Models\BordereauEngagement;
use App\Models\BonCommande;
use App\Models\Engagement;
use Illuminate\Http\Request;
use App\Models\OrdonnancePaiement;

class PdfDownloadController extends Controller
{
    public function __construct(private PdfGenerator $generator) {}

    public function telecharger(Request $request, string $etat, int $id)
    {
        $modelMap = [
            'certificat_engagement' => Engagement::class,
            'autorisation_engagement' => Engagement::class,
            'fiche_performance' => Engagement::class,
            'bordereau_engagement' => BordereauEngagement::class,
            'bon_commande' => BonCommande::class,
            'bon_commande_simple' => BonCommande::class,
            'ordonnance_paiement' => OrdonnancePaiement::class,
            'ordonnance_paiement_impot' => OrdonnancePaiement::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        // Charger les relations selon le type de modèle
        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'validateur',
                'engagements.beneficiaireFournisseur',
                'engagements.beneficiairePersonnel',
                'engagements.nomenclaturePrincipale',
                'engagements.lignes',
            ])->findOrFail($id);
        } elseif ($model === Engagement::class) {
            $record = $model::with([
                'budget',
                'nomenclaturePrincipale',
                'nomenclaturePrincipale.parent',
                'nomenclaturePrincipale.tache',
                'nomenclaturePrincipale.tache.activite',
                'nomenclaturePrincipale.tache.activite.action',
                'nomenclaturePrincipale.tache.activite.action.programme',
                'beneficiaire',
                'lignes',
                'engageable',
            ])->findOrFail($id);
        } elseif ($model === OrdonnancePaiement::class) {
            $record = $model::with([
                'engagement.nomenclaturePrincipale',
                'engagement.engageable.fournisseur',
                'beneficiaire', // ✅ Cette relation est OK si OrdonnancePaiement utilise morphTo
            ])->findOrFail($id);
        } elseif ($model === BonCommande::class) {
            $record = $model::with([
                'fournisseur',
                'serviceDemandeur',
                'lignes',
                'engagement',
            ])->findOrFail($id);
        } else {
            $record = $model::findOrFail($id);
        }

        return $this->generator->telecharger($etat, $record);
    }

    public function afficher(Request $request, string $etat, int $id)
    {
        $modelMap = [
            'certificat_engagement' => Engagement::class,
            'autorisation_engagement' => Engagement::class,
            'fiche_performance' => Engagement::class,
            'bordereau_engagement' => BordereauEngagement::class,
            'bon_commande' => BonCommande::class,
            'bon_commande_simple' => BonCommande::class,
            'ordonnance_paiement' => OrdonnancePaiement::class,
            'ordonnance_paiement_impot' => OrdonnancePaiement::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        // Charger les relations selon le type de modèle
        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'validateur',
                // ✅ CORRIGER ICI - Ligne 107
                'engagements.beneficiaireFournisseur',
                'engagements.beneficiairePersonnel',
                'engagements.nomenclaturePrincipale',
                'engagements.lignes',
            ])->findOrFail($id);
        } elseif ($model === Engagement::class) {
            $record = $model::with([
                'budget',
                'nomenclaturePrincipale',
                'nomenclaturePrincipale.parent',
                'nomenclaturePrincipale.tache',
                'nomenclaturePrincipale.tache.activite',
                'nomenclaturePrincipale.tache.activite.action',
                'nomenclaturePrincipale.tache.activite.action.programme',
                // ✅ CORRIGER ICI - Ligne 124
                'beneficiaireFournisseur',
                'beneficiairePersonnel',
                'lignes',
                'engageable',
            ])->findOrFail($id);
        } elseif ($model === OrdonnancePaiement::class) {
            $record = $model::with([
                'engagement.nomenclaturePrincipale',
                'engagement.engageable.fournisseur',
                'beneficiaire', // ✅ Cette relation est OK
            ])->findOrFail($id);
        } elseif ($model === BonCommande::class) {
            $record = $model::with([
                'fournisseur',
                'serviceDemandeur',
                'lignes',
                'engagement',
            ])->findOrFail($id);
        } else {
            $record = $model::findOrFail($id);
        }

        return $this->generator->afficher($etat, $record);
    }
}
