<?php

namespace App\Http\Controllers;

use App\Models\PrevisionRecette;
use App\Exports\PrevisionRecetteExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class PrevisionRecetteExportController extends Controller
{
    /**
     * Export Excel
     * GET /prevision-recette/{id}/export/excel
     */
    public function excel(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $previsionRecette = PrevisionRecette::with('exercice')->findOrFail($id);

        // Vérification d'accès
        abort_unless(auth()->user()?->can('view_prevision_recette'), 403);

        return Excel::download(
            new PrevisionRecetteExport($previsionRecette),
            'prevision_recette_' . $previsionRecette->code . '_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export PDF
     * GET /prevision-recette/{id}/export/pdf
     */
    public function pdf(int $id): \Illuminate\Http\Response
    {
        $previsionRecette = PrevisionRecette::with('exercice')->findOrFail($id);

        // Vérification d'accès
        abort_unless(auth()->user()?->can('view_prevision_recette'), 403);

        $lignes = $previsionRecette->lignesPrevisions()
            ->with('nomenclature')
            ->orderBy('ordre')
            ->get();

        $pdf = Pdf::loadView('exports.prevision-recette-pdf', [
            'prevision' => $previsionRecette,
            'lignes'    => $lignes,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download(
            'prevision_recette_' . $previsionRecette->code . '_' . now()->format('Ymd_His') . '.pdf'
        );
    }
}
