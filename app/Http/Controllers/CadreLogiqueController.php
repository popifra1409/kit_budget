<?php

namespace App\Http\Controllers;

use App\Exports\CadreLogiqueExport;
use App\Exports\CadreLogiquePdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CadreLogiqueController extends Controller
{
    public function telecharger(Request $request)
    {
        $programmeId = $request->input('programme_id');
        $annee = $request->input('annee');
        $format = $request->input('format', 'excel');

        if ($format === 'pdf') {
            $export = new CadreLogiquePdf($programmeId, $annee);
            return $export->download();
        } else {
            $export = new CadreLogiqueExport($programmeId, $annee);

            $filename = 'cadre_logique_' . $annee;
            if ($programmeId) {
                $programme = \App\Models\Programme::find($programmeId);
                $filename .= '_' . $programme->code;
            }
            $filename .= '.xlsx';

            return Excel::download($export, $filename);
        }
    }
}
