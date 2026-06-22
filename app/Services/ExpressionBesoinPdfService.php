<?php

namespace App\Services;

use App\Models\ExpressionBesoin;
use Barryvdh\DomPDF\Facade\Pdf;

class ExpressionBesoinPdfService
{
    public static function genererPdf(ExpressionBesoin $expressionBesoin)
    {
        $expressionBesoin->load([
            'serviceDemandeur',
            'responsableService',
            'comptableMatieres',
            'ordonnateur',
            'lignes.article.uniteMesure',
            'lignes.conditionnement',
        ]);

        $donnees = [
            '_raw'               => $expressionBesoin,
            'expression_besoin'  => $expressionBesoin,
            'lignes'             => $expressionBesoin->lignes,
        ];

        $pdf = Pdf::loadView('pdf.templates.expression-besoin', compact('donnees'));

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $pdf->setPaper('A4', 'portrait');

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
