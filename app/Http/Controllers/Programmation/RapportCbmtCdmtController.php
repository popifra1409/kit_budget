<?php

namespace App\Http\Controllers\Programmation;

use App\Http\Controllers\Controller;
use App\Models\CbmtLigne;
use App\Models\CdmtExercice;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RapportCbmtCdmtController extends Controller
{
    public function pdf(CdmtExercice $cdmt)
    {
        $cdmt->load(['cbmtExercice.planStrategiqueEp', 'lignes.sousProgrammeEp', 'lignes.action', 'lignes.activite']);

        $tableau9 = $cdmt->cbmtExercice->lignesRessources()->get()->groupBy('titre')
            ->map(fn($l, $t) => [
                'titre' => $t,
                'libelle' => CbmtLigne::TITRES_RESSOURCES[$t] ?? $l->first()->libelle_titre,
                'total_n_moins_1' => $l->sum('montant_n_moins_1'),
                'total_n' => $l->sum('montant_n'),
                'total_n_plus_1' => $l->sum('montant_n_plus_1'),
                'total_n_plus_2' => $l->sum('montant_n_plus_2'),
                'total_n_plus_3' => $l->sum('montant_n_plus_3'),
            ]);

        $tableau10 = $cdmt->cbmtExercice->lignesDepenses()->get()->groupBy('titre')
            ->map(fn($l, $t) => [
                'titre' => $t,
                'libelle' => CbmtLigne::TITRES_DEPENSES[$t] ?? $l->first()->libelle_titre,
                'total_n_moins_1' => $l->sum('montant_n_moins_1'),
                'total_n' => $l->sum('montant_n'),
                'total_n_plus_1' => $l->sum('montant_n_plus_1'),
                'total_n_plus_2' => $l->sum('montant_n_plus_2'),
                'total_n_plus_3' => $l->sum('montant_n_plus_3'),
            ]);

        $annexeC = $cdmt->lignes->groupBy('sous_programme_ep_id')->map(fn($lignesSp) => [
            'sous_programme' => $lignesSp->first()->sousProgrammeEp,
            'actions' => $lignesSp->groupBy('action_id')->map(fn($l) => [
                'action' => $l->first()->action,
                'lignes' => $l,
            ]),
        ]);

        $pdf = Pdf::loadView('filament.programmation.pdf.cbmt-cdmt', [
            'cdmt' => $cdmt,
            'tableau9' => $tableau9,
            'tableau10' => $tableau10,
            'ecart' => $cdmt->getEcartAvecCbmt(),
            'annexeC' => $annexeC,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("cbmt-cdmt-{$cdmt->numero}.pdf");
    }

    public function excel(CdmtExercice $cdmt)
    {
        return Excel::download(new \App\Exports\CbmtCdmtExport($cdmt), "cbmt-cdmt-{$cdmt->numero}.xlsx");
    }
}
