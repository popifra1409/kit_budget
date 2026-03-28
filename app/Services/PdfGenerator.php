<?php

namespace App\Services;

use App\Models\MemoireDepense;
use App\Models\ParametresStructure;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGenerator
{
    /**
     * Générer le PDF du mémoire de dépenses
     */
    public static function genererMemoireDepense(MemoireDepense $memoire): string
    {
        $memoire->load('lignes');
        $memoire->calculerTotaux();

        $donnees = self::preparerDonneesMemoireDepense($memoire);

        $pdf = Pdf::loadView('pdf.memoire-depense', compact('donnees'))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);

        $nomFichier   = 'memoire-depense-' . $memoire->numero . '.pdf';
        $cheminFichier = 'memoires-depense/' . $nomFichier;
        \Storage::put($cheminFichier, $pdf->output());

        $memoire->fichier_pdf = $cheminFichier;
        $memoire->saveQuietly();

        return $cheminFichier;
    }

    public static function telechargerMemoireDepense(MemoireDepense $memoire)
    {
        $memoire->load('lignes');
        $memoire->calculerTotaux();

        $donnees = self::preparerDonneesMemoireDepense($memoire);

        $pdf = Pdf::loadView('pdf.memoire-depense', compact('donnees'))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);

        return $pdf->download('memoire-depense-' . $memoire->numero . '.pdf');
    }

    public static function afficherMemoireDepense(MemoireDepense $memoire)
    {
        $memoire->load('lignes');
        $memoire->calculerTotaux();

        $donnees = self::preparerDonneesMemoireDepense($memoire);

        $pdf = Pdf::loadView('pdf.memoire-depense', compact('donnees'))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);

        return $pdf->stream('memoire-depense-' . $memoire->numero . '.pdf');
    }

    // ✅ Méthode helper — prépare $donnees au format attendu par le template
    private static function preparerDonneesMemoireDepense(MemoireDepense $memoire): array
    {
        return [
            '_raw'            => $memoire,
            'montant_lettres' => $memoire->montant_lettres
                ?? \App\Services\NombreEnLettres::convertir($memoire->montant_ttc ?? 0),
        ];
    }
}
