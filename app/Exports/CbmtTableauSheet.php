<?php

namespace App\Exports;

use App\Models\CbmtLigne;
use App\Models\CdmtExercice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CbmtTableauSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected CdmtExercice $cdmt, protected string $nature) {}

    public function headings(): array
    {
        return ['Titre', 'Libellé', 'N-1', 'N', 'N+1', 'N+2', 'N+3'];
    }

    public function array(): array
    {
        $lignes = $this->nature === 'ressource'
            ? $this->cdmt->cbmtExercice->lignesRessources()->get()
            : $this->cdmt->cbmtExercice->lignesDepenses()->get();

        $libelles = $this->nature === 'ressource' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        return $lignes->groupBy('titre')->map(fn($l, $t) => [
            $t,
            $libelles[$t] ?? $l->first()->libelle_titre,
            $l->sum('montant_n_moins_1'),
            $l->sum('montant_n'),
            $l->sum('montant_n_plus_1'),
            $l->sum('montant_n_plus_2'),
            $l->sum('montant_n_plus_3'),
        ])->values()->toArray();
    }

    public function title(): string
    {
        return $this->nature === 'ressource' ? 'Tableau 9' : 'Tableau 10';
    }
}
