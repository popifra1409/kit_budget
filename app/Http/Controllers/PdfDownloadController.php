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

    // ✅ Map type_document → Model class
    private const TYPE_MODEL_MAP = [
        'certificat_engagement'    => Engagement::class,
        'autorisation_engagement'  => Engagement::class,
        'fiche_performance'        => Engagement::class,
        'bordereau_engagement'     => BordereauEngagement::class,
        'bon_commande'             => BonCommande::class,
        'decision_administrative'  => DecisionAdministrative::class,
        'decision_previsionnelle'  => DecisionAdministrative::class,
        'ordonnance_paiement'      => OrdonnancePaiement::class,
        'ordonnance_paiement_impot' => OrdonnancePaiement::class,
    ];

    /**
     * Résoudre le model depuis le code d'état.
     */
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
     * Charger le record avec ses relations selon le model.
     * ✅ withoutGlobalScope('exercice') sur tous les modèles
     *    pour permettre l'accès aux documents des exercices clôturés (reports 2025→2026)
     */
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

            Engagement::class => $model::withoutGlobalScope('exercice')
                ->with([
                    'budget',
                    'exercice',
                    'nomenclaturePrincipale.parent',
                    'nomenclaturePrincipale.tache.activite.action.programme',
                    'beneficiaire',
                    'lignes.nomenclature',
                    'engageable',
                    'ordonnancesPaiement',
                ])
                ->findOrFail($id),

            OrdonnancePaiement::class => (function () use ($model, $id) {
                $record = $model::withoutGlobalScope('exercice')
                    ->with([
                        'engagement.nomenclaturePrincipale',
                        'engagement.exercice',
                        'engagement.budget',
                        'engagement.engageable',
                        'beneficiaire',
                        'exercice',
                    ])
                    ->findOrFail($id);

                // ✅ Charger les relations du document source sans scope exercice
                if ($record->engagement?->engageable instanceof BonCommande) {
                    $record->engagement->engageable->load('fournisseur');
                } elseif ($record->engagement?->engageable instanceof DecisionAdministrative) {
                    $record->engagement->engageable->load('personnel');
                }

                return $record;
            })(),

            BonCommande::class => $model::withoutGlobalScope('exercice')
                ->with([
                    'fournisseur',
                    'serviceDemandeur',
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

    /**
     * Télécharger le PDF
     */
    public function telecharger(Request $request, string $etat, int $id)
    {
        $model  = $this->resoudreModel($etat);
        $record = $this->chargerRecord($model, $id);

        return $this->generator->telecharger($etat, $record);
    }

    /**
     * Afficher le PDF dans le navigateur
     */
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
