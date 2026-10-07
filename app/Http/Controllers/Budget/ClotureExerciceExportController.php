<?php

namespace App\Http\Controllers\Budget;

use App\Exports\ClotureExerciceExport;
use App\Http\Controllers\Controller;
use App\Models\ClotureExercice;
use App\Models\ParametresStructure;
use App\Services\Budget\EtatsClotureService;
use App\Services\Budget\PeriodeExecutionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/** Exports de la clôture d'exercice : dossier PDF (conseil d'administration) et classeur Excel. */
class ClotureExerciceExportController extends Controller
{
    protected function autoriser(): void
    {
        abort_unless(auth()->user()?->can('view_any_cloture_exercice'), 403);
    }

    /** Données communes aux deux exports. */
    public static function donnees(ClotureExercice $cloture): array
    {
        $cloture->load(['exercice', 'budget', 'collectifReports']);
        $lignes = $cloture->lignes()->with('sousProgramme')->orderBy('titre')->orderBy('code')->get();

        return [
            'cloture'   => $cloture,
            'lignes'    => $lignes,
            'parTitre'  => $lignes->groupBy(fn($l) => $l->titre ?? 'sans'),
            'reports'   => $lignes->where('report_retenu', '>', 0)->values(),
            'etats'     => app(EtatsClotureService::class)->calculer($cloture->exercice, $cloture->budget),
            'periode'   => app(PeriodeExecutionService::class)->situation($cloture->exercice),
            'structure' => ParametresStructure::where('actif', true)->first() ?? ParametresStructure::first(),
        ];
    }

    protected function nomFichier(ClotureExercice $cloture, string $extension): string
    {
        return 'Cloture_' . $cloture->exercice->annee . '_' . ($cloture->budget->code ?? $cloture->budget_id) . '_' . now()->format('Ymd_His') . '.' . $extension;
    }

    public function pdf(ClotureExercice $cloture)
    {
        $this->autoriser();

        return Pdf::loadView('pdf.cloture-exercice', static::donnees($cloture))
            ->setPaper('A4', 'landscape')
            ->stream($this->nomFichier($cloture, 'pdf'));
    }

    public function excel(ClotureExercice $cloture)
    {
        $this->autoriser();

        return Excel::download(new ClotureExerciceExport(static::donnees($cloture)), $this->nomFichier($cloture, 'xlsx'));
    }
}
