<?php

namespace App\Services;

use App\Models\ExpressionBesoin;
use App\Models\EtatConfig;
use Barryvdh\DomPDF\Facade\Pdf;

class ExpressionBesoinPdfService
{
    public static function genererPdf(ExpressionBesoin $expressionBesoin)
    {
        // ✅ Même pattern que BonCommandePdfService
        $etatConfig = EtatConfig::where('code', 'expression_besoin')
            ->where('actif', true)
            ->first()
            ?? EtatConfig::where('type_document', 'expression_besoin')
            ->where('actif', true)
            ->where('est_defaut', true)
            ->first();

        $template = $etatConfig?->template ?? 'pdf.templates.expression-besoin';

        if (!view()->exists($template)) {
            throw new \Exception("Template introuvable : {$template}");
        }

        $expressionBesoin->load([
            'serviceDemandeur',
            'responsableService',
            'comptableMatieres',
            'ordonnateur',
            'signataireDg',
            'lignes.article.uniteMesure',
            'lignes.conditionnement',
        ]);

        $donnees = [
            '_raw'              => $expressionBesoin,
            '_etat_config'      => $etatConfig,
            'expression_besoin' => $expressionBesoin,
            'lignes'            => $expressionBesoin->lignes,
        ];

        $pdf = Pdf::loadView($template, compact('donnees'));

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        // ✅ Options PDF depuis EtatConfig si disponibles
        if ($etatConfig?->options_pdf) {
            $pdf->setPaper(
                $etatConfig->options_pdf['format_papier'] ?? 'A4',
                $etatConfig->options_pdf['orientation']   ?? 'portrait'
            );
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        return $pdf;
    }

    public static function telecharger(ExpressionBesoin $expressionBesoin)
    {
        return static::genererPdf($expressionBesoin)
            ->download("EB-{$expressionBesoin->numero}.pdf");
    }

    public static function apercu(ExpressionBesoin $expressionBesoin)
    {
        return static::genererPdf($expressionBesoin)
            ->stream("EB-{$expressionBesoin->numero}.pdf");
    }
}
