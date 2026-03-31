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
    public function __construct(private PdfGenerator $generator)
    {
    }

    // ✅ Map type_document → Model class
    // Toutes les variantes d'un type_document utilisent le même model
    private const TYPE_MODEL_MAP = [
        'certificat_engagement' => Engagement::class,
        'autorisation_engagement' => Engagement::class,
        'bordereau_engagement' => BordereauEngagement::class,
        'bon_commande' => BonCommande::class,
        'decision_administrative' => DecisionAdministrative::class,
        'decision_previsionnelle' => DecisionAdministrative::class,
        'ordonnance_paiement' => OrdonnancePaiement::class,
        'ordonnance_paiement_impot' => OrdonnancePaiement::class,
    ];

    /**
     * Résoudre le model depuis le code d'état.
     * Cherche d'abord par type_document, sinon par code direct.
     */
    private function resoudreModel(string $codeEtat): string
    {
        // Chercher le type_document depuis EtatConfig
        $config = EtatConfig::where('code', $codeEtat)->first();

        if ($config && $config->type_document) {
            $typeDoc = $config->type_document;
        } else {
            // Fallback — le code est peut-être directement un type_document
            $typeDoc = $codeEtat;
        }

        $model = self::TYPE_MODEL_MAP[$typeDoc] ?? null;

        if (!$model) {
            abort(404, "Aucun modèle trouvé pour l'état : {$codeEtat} (type: {$typeDoc})");
        }

        return $model;
    }

    /**
     * Charger le record avec ses relations selon le model
     */
    private function chargerRecord(string $model, int $id)
    {
        return match ($model) {
            BordereauEngagement::class => $model::with([
                'budget',
                'exercice',
                'emetteur',
                'validateur',
                'lignes' => fn($q) => $q->with('engagement.beneficiaire')->orderBy('numero_ligne'),
            ])->findOrFail($id),

            Engagement::class => $model::with([
                'budget',
                'exercice',
                'nomenclaturePrincipale.parent',
                'nomenclaturePrincipale.tache.activite.action.programme',
                'beneficiaire',
                'lignes.nomenclature',
                'engageable',
                'ordonnancesPaiement',
            ])->findOrFail($id),

            OrdonnancePaiement::class => (function () use ($model, $id) {
                    $record = $model::with([
                    'engagement.nomenclaturePrincipale',
                    'engagement.exercice',
                    'engagement.budget',
                    'engagement.engageable',
                    'beneficiaire',
                    'exercice',
                    ])->findOrFail($id);

                    if ($record->engagement?->engageable instanceof BonCommande) {
                        $record->engagement->engageable->load('fournisseur');
                    } elseif ($record->engagement?->engageable instanceof DecisionAdministrative) {
                        $record->engagement->engageable->load('personnel');
                    }

                    return $record;
                })(),

            BonCommande::class => $model::with([
                'fournisseur',
                'serviceDemandeur',
                'lignes',
                'engagement.beneficiaire',
            ])->findOrFail($id),

            DecisionAdministrative::class => $model::with([
                'personnel',
                'fournisseur',
                'typeDecision',
                'exercice',
                'budget',
                'engagement',
            ])->findOrFail($id),

            default => $model::findOrFail($id),
        };
    }

    public function telecharger(Request $request, string $etat, int $id)
    {
        $model = $this->resoudreModel($etat);
        $record = $this->chargerRecord($model, $id);

        return $this->generator->telecharger($etat, $record);
    }

    public function afficher(Request $request, string $etat, int $id)
    {
        try {
            $model = $this->resoudreModel($etat);
            $record = $this->chargerRecord($model, $id);

            return $this->generator->afficher($etat, $record);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }
}