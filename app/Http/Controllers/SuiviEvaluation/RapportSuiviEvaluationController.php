<?php

namespace App\Http\Controllers\SuiviEvaluation;

use App\Exports\RapportActivitePeriodiqueExport;
use App\Exports\Rap\RapExport;
use App\Http\Controllers\Controller;
use App\Models\RapportActivitePeriodique;
use App\Models\RapportAnnuelPerformance;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

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
}
