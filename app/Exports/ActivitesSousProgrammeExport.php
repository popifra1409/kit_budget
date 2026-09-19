<?php

namespace App\Exports;

use App\Models\SousProgrammeEp;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ActivitesSousProgrammeExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected SousProgrammeEp $sousProgramme,
        protected Collection $activites,
    ) {}

    public function headings(): array
    {
        return ['Désignation', 'Objectif', 'Indicateurs', 'Baseline', 'Cible', 'Zone d\'exécution', 'Responsable'];
    }

    public function collection()
    {
        return $this->activites->map(fn($act) => [
            $act->libelle,
            $act->objectif,
            $act->indicateurs->pluck('libelle')->implode(' | '),
            $act->indicateurs->pluck('valeur_reference')->filter()->implode(' | '),
            $act->indicateurs->pluck('valeur_cible')->filter()->implode(' | '),
            $act->zone_execution,
            $act->responsable?->name,
        ]);
    }
}
