<?php

namespace App\Http\Controllers;

use App\Services\PdfGenerator\PdfGenerator;
use App\Models\BordereauEngagement;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Models\Engagement;
use App\Models\OrdonnancePaiement;
use App\Models\EtatConfig;
use Illuminate\Http\Request;

class PdfDownloadController extends Controller
{
    public function __construct(private PdfGenerator $generator) {}

    private const TYPE_MODEL_MAP = [
        'certificat_engagement'     => Engagement::class,
        'autorisation_engagement'   => Engagement::class,
        'fiche_performance'         => Engagement::class,
        'bordereau_engagement'      => BordereauEngagement::class,
        'bon_commande'              => BonCommande::class,
        'decision_administrative'   => DecisionAdministrative::class,
        'decision_previsionnelle'   => DecisionAdministrative::class,
        'ordonnance_paiement'       => OrdonnancePaiement::class,
        'ordonnance_paiement_impot' => OrdonnancePaiement::class,
    ];

    private function resoudreModel(string $codeEtat): string
    {
        $config  = EtatConfig::where('code', $codeEtat)->first();
        $typeDoc = ($config?->type_document) ?: $codeEtat;

        $model = self::TYPE_MODEL_MAP[$typeDoc] ?? null;

        if (!$model) {
            abort(404, "Aucun modèle trouvé pour l'état : {$codeEtat} (type: {$typeDoc})");
        }

        return $model;
    }

    /**
     * ✅ Charger l'engageable d'un engagement sans global scope exercice.
     * Nécessaire pour les DA/BC des exercices clôturés (reports 2025→2026).
     */
    private function chargerEngageable(Engagement $engagement): void
    {
        if (!$engagement->engageable_type || !$engagement->engageable_id) return;

        $modelClass = $engagement->engageable_type;

        if ($engagement->estBonCommande()) {
            $engageable = $modelClass::withoutGlobalScope('exercice')
                ->with(['typeEngagement', 'fournisseur', 'lignes'])
                ->find($engagement->engageable_id);
        } elseif ($engagement->estDecision()) {
            $engageable = $modelClass::withoutGlobalScope('exercice')
                ->with(['typeDecision', 'personnel', 'fournisseur'])
                ->find($engagement->engageable_id);
        } else {
            $engageable = $modelClass::withoutGlobalScope('exercice')
                ->find($engagement->engageable_id);
        }

        $engagement->setRelation('engageable', $engageable);
    }

    private function chargerRecord(string $model, int $id)
    {
        return match ($model) {

            BordereauEngagement::class => $model::withoutGlobalScope('exercice')
                ->with([
                    'budget',
                    'exercice',
                    'emetteur',
                    'validateur',
                    'lignes' => fn($q) => $q
                        ->with('engagement.beneficiaire')
                        ->orderBy('numero_ligne'),
                ])
                ->findOrFail($id),

            // ✅ Engagement — engageable chargé via chargerEngageable()
            Engagement::class => (function () use ($model, $id) {
                $engagement = $model::withoutGlobalScope('exercice')
                    ->with([
                        'budget',
                        'exercice',
                        'nomenclaturePrincipale.parent',
                        'nomenclaturePrincipale.tache.activite.action.programme',
                        'beneficiaire',
                        'lignes.nomenclature',
                        'ordonnancesPaiement',
                    ])
                    ->findOrFail($id);

                $this->chargerEngageable($engagement);

                return $engagement;
            })(),

            // ✅ OrdonnancePaiement — idem pour l'engageable de l'engagement
            OrdonnancePaiement::class => (function () use ($model, $id) {
                $record = $model::withoutGlobalScope('exercice')
                    ->with([
                        'engagement.nomenclaturePrincipale',
                        'engagement.exercice',
                        'engagement.budget',
                        'beneficiaire',
                        'exercice',
                    ])
                    ->findOrFail($id);

                if ($record->engagement) {
                    $this->chargerEngageable($record->engagement);
                }

                return $record;
            })(),

            BonCommande::class => $model::withoutGlobalScope('exercice')
                ->with([
                    'fournisseur',
                    'serviceDemandeur',
                    'typeEngagement',
                    'lignes',
                    'engagement.beneficiaire',
                ])
                ->findOrFail($id),

            DecisionAdministrative::class => $model::withoutGlobalScope('exercice')
                ->with([
                    'personnel',
                    'fournisseur',
                    'typeDecision',
                    'exercice',
                    'budget',
                    'engagement',
                ])
                ->findOrFail($id),

            default => $model::withoutGlobalScope('exercice')->findOrFail($id),
        };
    }

    public function telecharger(Request $request, string $etat, int $id)
    {
        $model  = $this->resoudreModel($etat);
        $record = $this->chargerRecord($model, $id);

        return $this->generator->telecharger($etat, $record);
    }

    public function afficher(Request $request, string $etat, int $id)
    {
        try {
            $model  = $this->resoudreModel($etat);
            $record = $this->chargerRecord($model, $id);

            return $this->generator->afficher($etat, $record);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }
}
