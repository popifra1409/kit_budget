<?php

namespace App\Http\Controllers;

use App\Models\MemoireDepense;
use App\Services\PdfGenerator;

class MemoireDepenseController extends Controller
{
    /**
     * Générer et télécharger le PDF du mémoire
     */
    public function genererPdf(MemoireDepense $memoire)
    {
        try {
            return PdfGenerator::telechargerMemoireDepense($memoire);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }

    /**
     * Afficher le PDF en ligne
     */
    public function afficherPdf(MemoireDepense $memoire)
    {
        try {
            return PdfGenerator::afficherMemoireDepense($memoire);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'affichage du PDF : ' . $e->getMessage());
        }
    }
}
