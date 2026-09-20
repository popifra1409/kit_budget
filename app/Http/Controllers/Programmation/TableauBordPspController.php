<?php

namespace App\Http\Controllers\Programmation;

use App\Http\Controllers\Controller;
use App\Models\PlanStrategiqueEp;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class TableauBordPspController extends Controller
{
    public function pdf(PlanStrategiqueEp $psp)
    {
        $pdf = Pdf::loadView('filament.programmation.pdf.tableau-bord-psp', [
            'psp' => $psp,
            'synthese' => $psp->getSyntheseParExercice(),
            'cumul' => $psp->getCumulPluriannuel(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("tableau-bord-{$psp->code}.pdf");
    }

    public function excel(PlanStrategiqueEp $psp)
    {
        return Excel::download(
            new \App\Exports\TableauBordPspExport($psp),
            "tableau-bord-{$psp->code}.xlsx"
        );
    }
}
