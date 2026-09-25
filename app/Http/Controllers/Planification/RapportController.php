<?php

namespace App\Http\Controllers\Planification;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Activite;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RapportController extends Controller
{
    public function identificationSousProgrammesPdf(PlanStrategiqueEp $psp)
    {
        $psp->load(['sousProgrammes.responsable', 'sousProgrammes.programmeBudgetaire', 'sousProgrammes.indicateurs']);

        $pdf = Pdf::loadView('filament.planification.pdf.identification-sous-programmes', ['psp' => $psp])
            ->setPaper('a4', 'portrait');

        return $pdf->download("tableau14-identification-{$psp->code}.pdf");
    }

    public function identificationSousProgrammesExcel(PlanStrategiqueEp $psp)
    {
        $psp->load(['sousProgrammes.responsable', 'sousProgrammes.programmeBudgetaire', 'sousProgrammes.indicateurs']);

        return Excel::download(
            new \App\Exports\IdentificationSousProgrammesExport($psp),
            "tableau14-identification-{$psp->code}.xlsx"
        );
    }

    public function activitesSousProgrammePdf(SousProgrammeEp $sousProgramme)
    {
        $sousProgramme->load(['responsable', 'indicateurs']);
        $actionIds = $sousProgramme->actionsPourExercice()->pluck('id');
        $activites = Activite::whereIn('action_id', $actionIds)->with(['responsable', 'indicateurs'])->orderBy('ordre')->get();

        $pdf = Pdf::loadView('filament.planification.pdf.activites-sous-programme', [
            'sousProgramme' => $sousProgramme,
            'activites' => $activites,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("tableau15-activites-{$sousProgramme->code}.pdf");
    }

    public function activitesSousProgrammeExcel(SousProgrammeEp $sousProgramme)
    {
        $actionIds = $sousProgramme->actionsPourExercice()->pluck('id');
        $activites = Activite::whereIn('action_id', $actionIds)->with(['responsable', 'indicateurs'])->orderBy('ordre')->get();

        return Excel::download(
            new \App\Exports\ActivitesSousProgrammeExport($sousProgramme, $activites),
            "tableau15-activites-{$sousProgramme->code}.xlsx"
        );
    }
}
