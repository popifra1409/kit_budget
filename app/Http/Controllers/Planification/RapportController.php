<?php

namespace App\Http\Controllers\Planification;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Activite;
use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use App\Services\Planification\CadreLogiqueSousProgrammes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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

    // ── Cadre logique CDMT : tableau 1 + matrices par sous-programme ──────────

    public function cadreLogiquePdf(PlanStrategiqueEp $psp, Exercice $exercice, Request $request)
    {
        $rapport = $this->cadreLogique($psp, $exercice, $request);

        $pdf = Pdf::loadView('filament.planification.pdf.cadre-logique-sous-programmes', [
            'rapport' => $rapport,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->cadreLogiqueNom($rapport, 'pdf'));
    }

    public function cadreLogiqueExcel(PlanStrategiqueEp $psp, Exercice $exercice, Request $request)
    {
        $rapport = $this->cadreLogique($psp, $exercice, $request);

        return Excel::download(
            new \App\Exports\CadreLogique\CadreLogiqueCdmtExport($rapport),
            $this->cadreLogiqueNom($rapport, 'xlsx')
        );
    }

    protected function cadreLogique(PlanStrategiqueEp $psp, Exercice $exercice, Request $request): CadreLogiqueSousProgrammes
    {
        $sousProgrammeId = $request->integer('sousProgramme');

        return new CadreLogiqueSousProgrammes(
            $psp,
            $exercice,
            $sousProgrammeId ? SousProgrammeEp::find($sousProgrammeId) : null,
        );
    }

    protected function cadreLogiqueNom(CadreLogiqueSousProgrammes $rapport, string $extension): string
    {
        $code = $rapport->filtreUnSousProgramme()
            ? $rapport->sousProgramme->code
            : $rapport->psp->code;

        return "cadre-logique-{$code}-{$rapport->anneeReference()}.{$extension}";
    }
}
