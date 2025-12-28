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
        // Récupérer les paramètres de structure
        $structure = ParametresStructure::getParametres();

        if (!$structure) {
            throw new \Exception('Paramètres de structure non configurés');
        }

        // Charger les lignes
        $memoire->load('lignes');

        // Recalculer les totaux
        $memoire->calculerTotaux();

        // Générer le PDF
        $pdf = Pdf::loadView('pdf.memoire-depense', [
            'memoire' => $memoire,
            'structure' => $structure,
        ]);

        // Configuration du PDF
        $pdf->setPaper('A4', 'portrait');

        // Nom du fichier
        $nomFichier = 'memoire-depense-' . $memoire->numero . '.pdf';
        $cheminFichier = 'memoires-depense/' . $nomFichier;

        // Sauvegarder le PDF
        Storage::disk('public')->put($cheminFichier, $pdf->output());

        // Mettre à jour le mémoire avec le chemin du PDF
        $memoire->fichier_pdf = $cheminFichier;
        $memoire->saveQuietly();

        return $cheminFichier;
    }

    /**
     * Télécharger le PDF du mémoire
     */
    public static function telechargerMemoireDepense(MemoireDepense $memoire)
    {
        $structure = ParametresStructure::getParametres();

        if (!$structure) {
            throw new \Exception('Paramètres de structure non configurés');
        }

        $memoire->load('lignes');
        $memoire->calculerTotaux();

        $pdf = Pdf::loadView('pdf.memoire-depense', [
            'memoire' => $memoire,
            'structure' => $structure,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('memoire-depense-' . $memoire->numero . '.pdf');
    }

    /**
     * Afficher le PDF en ligne
     */
    public static function afficherMemoireDepense(MemoireDepense $memoire)
    {
        $structure = ParametresStructure::getParametres();

        if (!$structure) {
            throw new \Exception('Paramètres de structure non configurés');
        }

        $memoire->load('lignes');
        $memoire->calculerTotaux();

        $pdf = Pdf::loadView('pdf.memoire-depense', [
            'memoire' => $memoire,
            'structure' => $structure,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('memoire-depense-' . $memoire->numero . '.pdf');
    }
}
