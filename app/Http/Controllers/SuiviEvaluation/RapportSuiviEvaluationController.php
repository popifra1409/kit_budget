<?php

namespace App\Http\Controllers\SuiviEvaluation;

use App\Exports\RapportActivitePeriodiqueExport;
use App\Exports\Rap\RapExport;
use App\Http\Controllers\Controller;
use App\Models\RapportActivitePeriodique;
use App\Models\RapportAnnuelPerformance;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MatriceArrimageExport;
use App\Models\PlanStrategiqueEp;
use App\Services\SuiviEvaluation\MatriceArrimageService;
use Illuminate\Http\Request;

class RapportSuiviEvaluationController extends Controller
{
    // ── Annexe 9 : Rapport d'activité périodique ──────────────
    public function activitePdf(RapportActivitePeriodique $rapport)
    {
        abort_unless(auth()->user()->can('view_rapport_activite_periodique'), 403);

        $rapport->load(['activite.action', 'activite.responsable', 'lignes']);

        return Pdf::loadView('filament.suivi-evaluation.pdf.rapport-activite', [
            'rapport'       => $rapport,
            'syntheseTache' => $rapport->getSynthese('tache'),
            'syntheseMoyen' => $rapport->getSynthese('moyen'),
        ])->setPaper('a4', 'portrait')
            ->download("rapport-activite-{$rapport->numero}.pdf");
    }

    public function activiteExcel(RapportActivitePeriodique $rapport)
    {
        abort_unless(auth()->user()->can('view_rapport_activite_periodique'), 403);

        return Excel::download(new RapportActivitePeriodiqueExport($rapport), "rapport-activite-{$rapport->numero}.xlsx");
    }

    // ── Annexe 10 : RAP ──────────────────────────────────────
    public function rapPdf(RapportAnnuelPerformance $rap)
    {
        abort_unless(auth()->user()->can('view_rapport_annuel_performance'), 403);

        $rap->load(['planStrategiqueEp', 'exercice', 'ppaExercice']);
        $etat = $rap->getEtatMiseEnOeuvre();

        return Pdf::loadView('filament.suivi-evaluation.pdf.rap', [
            'rap'     => $rap,
            'etat'    => $etat,
            'totaux'  => $rap->getTotauxExecution($etat),
        ])->setPaper('a4', 'landscape')
            ->download("rap-{$rap->numero}.pdf");
    }

    public function rapExcel(RapportAnnuelPerformance $rap)
    {
        abort_unless(auth()->user()->can('view_rapport_annuel_performance'), 403);

        return Excel::download(new RapExport($rap), "rap-{$rap->numero}.xlsx");
    }

    // ── Matrice d'arrimage stratégique ──────────────────────
    public function matricePdf(Request $request, MatriceArrimageService $service)
    {
        $m = $this->resoudreMatrice($request, $service);

        return Pdf::loadView('filament.suivi-evaluation.matrice.pdf', ['m' => $m])
            ->setPaper('a3', 'landscape')
            ->download("matrice-arrimage-{$m['exercice']?->annee}.pdf");
    }

    public function matriceExcel(Request $request, MatriceArrimageService $service)
    {
        $m = $this->resoudreMatrice($request, $service);

        return Excel::download(new MatriceArrimageExport($m), "matrice-arrimage-{$m['exercice']?->annee}.xlsx");
    }

    /**
     * Parametres valides + restriction par responsable appliquee par le service :
     * modifier ?sous_programme= dans l'URL ne donne acces a rien de plus.
     */
    protected function resoudreMatrice(Request $request, MatriceArrimageService $service): array
    {
        abort_unless($request->user()->can('exporter_matrice_arrimage'), 403);

        $v = $request->validate([
            'psp'            => ['required', 'integer', 'exists:plans_strategiques_ep,id'],
            'exercice'       => ['required', 'integer', 'exists:exercices,id'],
            'sous_programme' => ['nullable', 'integer'],
        ]);

        $m = $service->construire(
            PlanStrategiqueEp::findOrFail($v['psp']),
            (int) $v['exercice'],
            $request->user(),
            isset($v['sous_programme']) ? (int) $v['sous_programme'] : null
        );

        abort_if($m['sous_programmes']->isEmpty(), 404, 'Aucun sous-programme accessible pour ces critères.');

        return $m;
    }
}
