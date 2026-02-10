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

        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'validateur',
                'engagements' => function ($query) {
                    $query->with([
                        'beneficiaire',  
                        'nomenclaturePrincipale',
                        'lignes',
                        'engageable', 
                    ]);
                },
            ])->findOrFail($id);
        } elseif ($model === Engagement::class) {
            $record = $model::with([
                'budget',
                'exercice',
                'nomenclaturePrincipale',
                'nomenclaturePrincipale.parent',
                'nomenclaturePrincipale.tache',
                'nomenclaturePrincipale.tache.activite',
                'nomenclaturePrincipale.tache.activite.action',
                'nomenclaturePrincipale.tache.activite.action.programme',
                'beneficiaire', 
                'lignes.nomenclature',
                'engageable',
                'ordonnancesPaiement',
            ])->findOrFail($id);

            if ($record->engageable) {
                if ($record->engageable instanceof \App\Models\BonCommande) {
                    $record->engageable->load('fournisseur', 'lignes');
                } elseif ($record->engageable instanceof \App\Models\DecisionAdministrative) {
                    $record->engageable->load('personnel', 'typeDecision');
                }
            }
        } elseif ($model === OrdonnancePaiement::class) {
            $record = $model::with([
                'engagement' => function ($query) {
                    $query->with([
                        'nomenclaturePrincipale',
                        'exercice',
                        'budget',
                        'engageable', 
                    ]);
                },
                'beneficiaire',  
            ])->findOrFail($id);

            if ($record->engagement && $record->engagement->engageable) {
                $engageable = $record->engagement->engageable;

                if ($engageable instanceof \App\Models\BonCommande) {
                    $engageable->load('fournisseur');
                } elseif ($engageable instanceof \App\Models\DecisionAdministrative) {
                    $engageable->load('personnel');
                }
            }
        } elseif ($model === BonCommande::class) {
            $record = $model::with([
                'fournisseur',
                'serviceDemandeur',
                'lignes',
                'engagement.beneficiaire', 
            ])->findOrFail($id);
        } else {
            $record = $model::findOrFail($id);
        }

        return $this->generator->afficher($etat, $record);
    }
}
