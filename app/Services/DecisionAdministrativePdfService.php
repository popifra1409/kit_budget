<?php

namespace App\Services;

use App\Models\DecisionAdministrative;
use App\Models\EtatConfig;
use Barryvdh\DomPDF\Facade\Pdf;

class DecisionAdministrativePdfService
{
    /**
     * Générer le PDF d'une décision administrative
     *
     * @param DecisionAdministrative $decision
     * @param string $typeEtat Type d'état
     * @return string
     */
    public static function genererPdf(DecisionAdministrative $decision, string $typeEtat = 'standard'): string
    {
        // Récupérer la configuration d'état
        $codeEtat = 'decision_administrative';

        $etatConfig = EtatConfig::where('code', $codeEtat)
            ->where('actif', true)
            ->first();

        if (!$etatConfig) {
            // Fallback sur un template par défaut
            $template = 'etats.decision-administrative';
        } else {
            $template = $etatConfig->template ?? 'etats.decision-administrative';
        }

        // Générer le PDF
        $pdf = Pdf::loadView($template, [
            'decision' => $decision,
            'da' => $decision,
            'exercice' => $decision->exercice,
            'budget' => $decision->budget,
            'personnel' => $decision->personnel,
            'service' => $decision->serviceEmetteur,
        ]);

        // Configuration du PDF
        if ($etatConfig) {
            $pdf->setPaper(
                $etatConfig->format_papier ?? 'A4',
                $etatConfig->orientation ?? 'portrait'
            );
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        return $pdf->output();
    }

    /**
     * Télécharger le PDF
     */
    public static function telecharger(DecisionAdministrative $decision, string $typeEtat = 'standard')
    {
        $pdf = static::genererPdf($decision, $typeEtat);
        $filename = "DA-{$decision->numero}.pdf";

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf;
            },
            $filename
        );
    }

    /**
     * Afficher en aperçu
     */
    public static function apercu(DecisionAdministrative $decision, string $typeEtat = 'standard')
    {
        $pdf = static::genererPdf($decision, $typeEtat);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="DA-' . $decision->numero . '.pdf"');
    }
}
