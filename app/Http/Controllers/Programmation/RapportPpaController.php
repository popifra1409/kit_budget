<?php

namespace App\Http\Controllers\Programmation;

use App\Http\Controllers\Controller;
use App\Models\PpaExercice;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RapportPpaController extends Controller
{
    public function pdf(PpaExercice $ppa)
    {
        $ppa->load(['planStrategiqueEp.cspMinistere', 'exercice']);

        $pdf = Pdf::loadView('filament.programmation.pdf.ppa', [
            'ppa' => $ppa,
            'sousProgrammes' => $ppa->getSousProgrammesAvecActivites(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("ppa-{$ppa->numero}.pdf");
    }

    public function excel(PpaExercice $ppa)
    {
        return Excel::download(
            new \App\Exports\PpaExport($ppa),
            "ppa-{$ppa->numero}.xlsx"
        );
    }
}
