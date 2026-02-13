<?php

namespace App\Http\Controllers;

use App\Services\PdfGenerator\PdfGenerator;
use App\Models\BordereauEngagement;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Models\Engagement;
use App\Models\OrdonnancePaiement;
use Illuminate\Http\Request;

class PdfDownloadController extends Controller
{
    public function __construct(private PdfGenerator $generator) {}

    public function telecharger(Request $request, string $etat, int $id)
    {
        $modelMap = [
            'certificat_engagement'        => Engagement::class,
            'autorisation_engagement'      => Engagement::class,
            'fiche_performance'            => Engagement::class,
            'bordereau_engagement'         => BordereauEngagement::class,
            'bon_commande'                 => BonCommande::class,
            'bon_commande_simple'          => BonCommande::class,
            'ordonnance_paiement'          => OrdonnancePaiement::class,
            'ordonnance_paiement_impot'    => OrdonnancePaiement::class,
            // 'engagement'                   => BordereauEngagement::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'exercice',
                'emetteur',
                'validateur',
                'lignes' => function ($query) {
                    $query->with('engagement.beneficiaire')
                        ->orderBy('numero_ligne');
                },
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
            'certificat_engagement'        => Engagement::class,
            'autorisation_engagement'      => Engagement::class,
            'fiche_performance'            => Engagement::class,
            'bordereau_engagement'         => BordereauEngagement::class,
            'bon_commande'                 => BonCommande::class,
            'bon_commande_simple'          => BonCommande::class,
            'ordonnance_paiement'          => OrdonnancePaiement::class,
            'ordonnance_paiement_impot'    => OrdonnancePaiement::class,
            'decision_administrative'      => DecisionAdministrative::class,
            // 'engagement'                   => BordereauEngagement::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        try {
            if ($model === BordereauEngagement::class) {
                $record = $model::with([
                    'budget',
                    'exercice',
                    'emetteur',
                    'validateur',
                    'lignes' => function ($query) {
                        $query->with('engagement.beneficiaire')
                            ->orderBy('numero_ligne');
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
                    if ($record->engageable instanceof BonCommande) {
                        $record->engageable->load('fournisseur', 'lignes');
                    } elseif ($record->engageable instanceof DecisionAdministrative) {
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

                    if ($engageable instanceof BonCommande) {
                        $engageable->load('fournisseur');
                    } elseif ($engageable instanceof DecisionAdministrative) {
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
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Erreur lors de la génération du PDF');
        }
    }
}
