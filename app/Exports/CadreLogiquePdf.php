<?php

namespace App\Exports;

use App\Models\Tache;
use Barryvdh\DomPDF\Facade\Pdf;

class CadreLogiquePdf
{
    protected $programmeId;
    protected $annee;

    public function __construct($programmeId = null, $annee = null)
    {
        $this->programmeId = $programmeId;
        $this->annee = $annee ?? now()->year;
    }

    /**
     * Générer le PDF
     */
    public function generate()
    {
        $taches = $this->getData();

        $pdf = Pdf::loadView('exports.cadre-logique-pdf', [
            'taches' => $taches,
            'annee' => $this->annee,
            'titre' => $this->getTitre()
        ]);

        // Format paysage (landscape) pour plus de colonnes
        $pdf->setPaper('a4', 'landscape');

        return $pdf;
    }

    /**
     * Récupérer les données
     */
    protected function getData()
    {
        $query = Tache::with([
            'activite.action.programme.objectifsPrincipaux',
            'activite.action.objectifsSpecifiques',
            'nomenclature'
        ]);

        // Filtrer par programme si spécifié
        if ($this->programmeId) {
            $query->whereHas('activite.action.programme', function ($q) {
                $q->where('id', $this->programmeId);
            });
        }

        // Filtrer par année
        $query->whereHas('activite.action.programme', function ($q) {
            $q->where('annee', $this->annee);
        });

        return $query->orderBy('id')->get();
    }

    /**
     * Obtenir le titre du document
     */
    protected function getTitre()
    {
        if ($this->programmeId) {
            $programme = \App\Models\Programme::find($this->programmeId);
            return "Cadre Logique {$this->annee} - {$programme->code} - {$programme->libelle}";
        }

        return "Cadre Logique {$this->annee}";
    }

    /**
     * Télécharger le PDF
     */
    public function download()
    {
        $filename = 'cadre_logique_' . $this->annee;
        if ($this->programmeId) {
            $programme = \App\Models\Programme::find($this->programmeId);
            $filename .= '_' . $programme->code;
        }
        $filename .= '.pdf';

        return $this->generate()->download($filename);
    }

    /**
     * Afficher le PDF dans le navigateur
     */
    public function stream()
    {
        $filename = 'cadre_logique_' . $this->annee . '.pdf';
        return $this->generate()->stream($filename);
    }
}
