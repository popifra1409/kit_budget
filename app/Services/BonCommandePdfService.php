<?php

namespace App\Services;

use App\Models\BonCommande;
use App\Models\EtatConfig;
use Barryvdh\DomPDF\Facade\Pdf;

class BonCommandePdfService
{
    public static function genererPdf(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        // ✅ DEBUG 1 : Voir ce qui arrive
        \Log::info('=== DEBUT genererPdf ===');
        \Log::info('Type État reçu', ['typeEtat' => $typeEtat]);

        // Déterminer le code d'état
        $codeEtat = match ($typeEtat) {
            'simple' => 'bon-commande-simple',
            'simple_preimprime' => 'bon-commande-simple-2',
            'complet' => 'bon-commande',
            default => 'bon-commande-simple',
        };

        \Log::info('Code État calculé', ['codeEtat' => $codeEtat]);

        // Récupérer la configuration
        $etatConfig = EtatConfig::where('code', $codeEtat)
            ->where('actif', true)
            ->first();

        \Log::info('EtatConfig trouvé', [
            'found' => $etatConfig ? 'OUI' : 'NON',
            'template_db' => $etatConfig?->template,
        ]);

        // ✅ Déterminer le template
        if ($etatConfig && $etatConfig->template) {
            $template = $etatConfig->template;
            \Log::info('Template depuis DB', ['template' => $template]);
        } else {
            $template = match ($typeEtat) {
                'simple' => 'pdf.templates.bon-commande-simple',
                'simple_preimprime' => 'pdf.templates.bon-commande-simple2',
                'complet' => 'pdf.templates.bon-commande',
                default => 'pdf.templates.bon-commande-simple',
            };
            \Log::info('Template FALLBACK', ['template' => $template]);
        }

        // ✅ Vérifier si le template existe
        $templateExists = view()->exists($template);
        \Log::info('Template existe ?', ['exists' => $templateExists, 'template' => $template]);

        if (!$templateExists) {
            \Log::error('TEMPLATE INTROUVABLE', ['template' => $template]);
            throw new \Exception("Template introuvable : {$template}");
        }

        \Log::info('Template final utilisé', ['template' => $template]);

        // Charger les relations
        //if (!$bonCommande->relationLoaded('fournisseur')) {
        $bonCommande->load([
            'exercice',
            'budget',
            'fournisseur.regimeFiscal',
            'serviceDemandeur',
            'lignes.nomenclature',
            'engagement.nomenclaturePrincipale',
            'typeEngagement',
        ]);
        //}

        // Préparer les données
        $donnees = [
            '_raw' => $bonCommande,
            'bon_commande' => $bonCommande,
            'fournisseur' => $bonCommande->fournisseur,
            'lignes' => $bonCommande->lignes,
            'exercice' => $bonCommande->exercice,
        ];

        // Générer le PDF
        $pdf = Pdf::loadView($template, compact('donnees'));
        //$pdf = Pdf::loadView($template, $donnees);

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        if ($etatConfig && $etatConfig->options_pdf) {
            $pdf->setPaper(
                $etatConfig->options_pdf['format_papier'] ?? 'A4',
                $etatConfig->options_pdf['orientation'] ?? 'portrait'
            );
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        \Log::info('=== FIN genererPdf ===');

        return $pdf;
    }

    public static function telecharger(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);

        $filename = match ($typeEtat) {
            'simple' => "BC-{$bonCommande->numero}.pdf",
            'simple_preimprime' => "BC-PREIMPRIME-{$bonCommande->numero}.pdf",
            'complet' => "BCA-{$bonCommande->numero}.pdf",
            default => "BC-{$bonCommande->numero}.pdf",
        };

        return $pdf->download($filename);
    }

    public static function apercu(BonCommande $bonCommande, string $typeEtat = 'simple')
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);

        $filename = match ($typeEtat) {
            'simple' => "BC-{$bonCommande->numero}.pdf",
            'simple_preimprime' => "BC-PREIMPRIME-{$bonCommande->numero}.pdf",
            'complet' => "BCA-{$bonCommande->numero}.pdf",
            default => "BC-{$bonCommande->numero}.pdf",
        };

        return $pdf->stream($filename);
    }

    public static function getOutput(BonCommande $bonCommande, string $typeEtat = 'simple'): string
    {
        $pdf = static::genererPdf($bonCommande, $typeEtat);
        return $pdf->output();
    }

    /**
     * Obtenir la liste des types d'états disponibles
     */
    public static function getTypesEtatsDisponibles(): array
    {
        return [
            'simple' => 'Bon de commande simple (avec en-tête)',
            'simple_preimprime' => 'Bon de commande simple (papier préimprimé)',
            'complet' => 'Bon de commande complet',
        ];
    }
}
