<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\EtatConfig;
use Barryvdh\DomPDF\Facade\Pdf;

class BonCommandePdfService
{
    /**
     * Générer le PDF d'un bon de commande
     *
     * @param BonCommande $bonCommande
     * @param string $typeEtat 'simple' ou 'complet'
     * @return \Barryvdh\DomPDF\PDF
     */
    public static function genererPdf(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        // Déterminer le code d'état et le template
        $codeEtat = $typeEtat === 'simple'
            ? 'bon_commande_simple'
            : 'bon_commande';

        // Récupérer la configuration d'état
        $etatConfig = EtatConfig::where('code', $codeEtat)
            ->where('actif', true)
            ->first();

        if (!$etatConfig) {
            // Fallback sur les templates par défaut
            $template = $typeEtat === 'simple'
                ? 'pdf.templates.bon-commande-simple'
                : 'pdf.templates.bon-commande';
        } else {
            $template = $etatConfig->template;
        }

        // Charger toutes les relations nécessaires
        $bonCommande->load([
            'exercice',
            'budget',
            'fournisseur.regimeFiscal',
            'serviceDemandeur',
            'lignes.nomenclature',
            'engagement.nomenclaturePrincipale',
            'typeEngagement',
        ]);

        // Préparer les données dans le format attendu par le template
        $donnees = [
            '_raw' => $bonCommande,
        ];

        // Générer le PDF avec les options d'encodage UTF-8
        $pdf = Pdf::loadView($template, compact('donnees'));

        // Ajouter les options d'encodage
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
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

        return $pdf;
    }

    /**
     * Télécharger le PDF
     */
    public static function telecharger(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);
        $suffix = $typeEtat === 'simple' ? '-simple' : '';
        $filename = "BC-{$bonCommande->numero}{$suffix}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Afficher en aperçu dans le navigateur
     */
    public static function apercu(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);
        $suffix = $typeEtat === 'simple' ? '-simple' : '';
        $filename = "BC-{$bonCommande->numero}{$suffix}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Obtenir le contenu du PDF en tant que chaîne
     * (Pour stockage ou envoi par email)
     */
    public static function getOutput(BonCommande $bonCommande, string $typeEtat = 'simple'): string
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);

        return $pdf->output();
    }
}
