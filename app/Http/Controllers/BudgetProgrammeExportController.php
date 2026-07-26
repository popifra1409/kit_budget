<?php

namespace App\Http\Controllers;

use App\Exports\BudgetProgrammeExport;
use App\Services\BudgetProgrammeService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BudgetProgrammeExportController extends Controller
{
    public function __construct(private BudgetProgrammeService $service) {}

    /**
     * Export Excel — Budget Programme Triennal (Dépenses)
     */
    public function exportExcel(Request $request)
    {
        $annee    = (int) $request->get('annee', now()->year);
        $filename = "Budget_Programme_{$annee}_" . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new BudgetProgrammeExport($annee),
            $filename
        );
    }

    /**
     * Preview JSON — données pour l'interface
     */
    public function preview(Request $request)
    {
        $annee    = (int) $request->get('annee', now()->year);
        $donnees  = $this->service->collecterDonnees($annee);

        return response()->json($donnees);
    }
}
