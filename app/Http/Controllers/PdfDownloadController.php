<?php

namespace App\Http\Controllers;

use App\Services\PdfGenerator\PdfGenerator;
use App\Models\BordereauEngagement;
use App\Models\BonCommande;
use Illuminate\Http\Request;

class PdfDownloadController extends Controller
{
    public function __construct(private PdfGenerator $generator) {}

    public function telecharger(Request $request, string $etat, int $id)
    {
        $modelMap = [
            'certificat_engagement' => BordereauEngagement::class,
            'autorisation_engagement' => BordereauEngagement::class,
            'bon_commande' => BonCommande::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        // Charger les relations pour BordereauEngagement
        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'validateur',
                'engagements.engageable.lignes.nomenclature.parent',
                'engagements.engageable.lignes.nomenclature.tache.activite.action.programme',
            ])->findOrFail($id);
        } else {
            $record = $model::findOrFail($id);
        }

        return $this->generator->telecharger($etat, $record);
    }

    public function afficher(Request $request, string $etat, int $id)
    {
        $modelMap = [
            'certificat_engagement' => BordereauEngagement::class,
            'autorisation_engagement' => BordereauEngagement::class,
            'bon_commande' => BonCommande::class,
        ];

        if (!isset($modelMap[$etat])) {
            abort(404, 'État non trouvé');
        }

        $model = $modelMap[$etat];

        // Charger les relations pour BordereauEngagement
        if ($model === BordereauEngagement::class) {
            $record = $model::with([
                'budget',
                'validateur',
                'engagements.engageable.lignes.nomenclature.parent',
                'engagements.engageable.lignes.nomenclature.tache.activite.action.programme',
            ])->findOrFail($id);
        } else {
            $record = $model::findOrFail($id);
        }

        return $this->generator->afficher($etat, $record);
    }
}
