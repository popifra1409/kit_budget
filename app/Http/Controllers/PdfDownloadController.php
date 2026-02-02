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
            // États liés aux Engagements individuels
            'certificat_engagement' => Engagement::class,
            'autorisation_engagement' => Engagement::class,
            'fiche_performance' => Engagement::class,

            // État lié au Bordereau (liste des engagements)
            'bordereau_engagement' => BordereauEngagement::class,

            // États liés aux Bons de Commande
            'bon_commande' => BonCommande::class,
            'bon_commande_simple' => BonCommande::class,

            // États liés aux Ordonnances de paiement
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
                'engagements.beneficiaire',
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
            // ✅ CORRECTION : Charger engageable au lieu de bonCommande
            $record = $model::with([
                'engagement.nomenclaturePrincipale',
                'engagement.engageable.fournisseur', // ✅ engageable au lieu de bonCommande
                'beneficiaire',
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
            // États liés aux Engagements individuels
            'certificat_engagement' => Engagement::class,
            'autorisation_engagement' => Engagement::class,
            'fiche_performance' => Engagement::class,

            // État lié au Bordereau (liste des engagements)
            'bordereau_engagement' => BordereauEngagement::class,

            // États liés aux Bons de Commande
            'bon_commande' => BonCommande::class,
            'bon_commande_simple' => BonCommande::class,

            // États liés aux Ordonnances de paiement
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
                'engagements.beneficiaire',
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
            // ✅ CORRECTION : Charger engageable au lieu de bonCommande
            $record = $model::with([
                'engagement.nomenclaturePrincipale',
                'engagement.engageable.fournisseur', // ✅ engageable au lieu de bonCommande
                'beneficiaire',
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
    