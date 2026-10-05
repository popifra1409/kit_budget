<?php

namespace App\Http\Controllers\Programmation;

use App\Exports\CbmtExport;
use App\Http\Controllers\Controller;
use App\Models\CbmtExercice;
use App\Models\ParametresStructure;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exports du CBMT seul (sans CDMT) : PDF paysage et Excel.
 * Mêmes partiels et même calcul que l'écran « Rapport CBMT/CDMT ».
 */
class RapportCbmtController extends Controller
{
    protected function autoriser(): void
    {
        // Même règle que l'accès au module Programmation
        abort_unless(
            auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->can('access_module_programmation')),
            403
        );
    }

    protected function nomFichier(CbmtExercice $cbmt, string $extension): string
    {
        return 'CBMT_' . $cbmt->anneeReference() . '_' . $cbmt->numero . '_' . now()->format('Ymd_His') . '.' . $extension;
    }

    public function pdf(CbmtExercice $cbmt)
    {
        $this->autoriser();

        $cbmt->load(['planStrategiqueEp', 'exerciceReference']);

        return Pdf::loadView('filament.programmation.pdf.cbmt', [
            'cbmt'      => $cbmt,
            'structure' => ParametresStructure::where('actif', true)->first(),
        ])
            ->setPaper('A4', 'landscape')
            ->stream($this->nomFichier($cbmt, 'pdf'));
    }

    public function excel(CbmtExercice $cbmt)
    {
        $this->autoriser();

        return Excel::download(new CbmtExport($cbmt), $this->nomFichier($cbmt, 'xlsx'));
    }
}
