<?php

namespace App\Http\Controllers\Planification;

use App\Exports\ArborescenceLibellesExport;
use App\Http\Controllers\Controller;
use App\Models\PlanStrategiqueEp;
use App\Services\Planification\ArborescenceLibellesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ArborescenceLibellesController extends Controller
{
    public function pdf(Request $request, ArborescenceLibellesService $service)
    {
        $t = $this->resoudre($request, $service);

        return Pdf::loadView('filament.planification.libelles.pdf', ['t' => $t])
            ->setPaper('a3', 'landscape')
            ->download("tableau-libelles-{$t['exercice']?->annee}.pdf");
    }

    public function excel(Request $request, ArborescenceLibellesService $service)
    {
        $t = $this->resoudre($request, $service);

        return Excel::download(new ArborescenceLibellesExport($t), "tableau-libelles-{$t['exercice']?->annee}.xlsx");
    }

    /** Parametres valides + restriction par responsable appliquee par le service. */
    protected function resoudre(Request $request, ArborescenceLibellesService $service): array
    {
        abort_unless($request->user()->can('exporter_arborescence_libelles'), 403);

        $v = $request->validate([
            'psp'            => ['required', 'integer', 'exists:plans_strategiques_ep,id'],
            'exercice'       => ['required', 'integer', 'exists:exercices,id'],
            'sous_programme' => ['nullable', 'integer'],
        ]);

        $t = $service->construire(
            PlanStrategiqueEp::findOrFail($v['psp']),
            (int) $v['exercice'],
            $request->user(),
            isset($v['sous_programme']) ? (int) $v['sous_programme'] : null
        );

        abort_if($t['sous_programmes']->isEmpty(), 404, 'Aucun sous-programme accessible pour ces critères.');

        return $t;
    }
}
