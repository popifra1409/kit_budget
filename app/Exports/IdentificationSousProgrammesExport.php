<?php

namespace App\Exports;

use App\Models\PlanStrategiqueEp;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IdentificationSousProgrammesExport implements FromCollection, WithHeadings
{
    public function __construct(protected PlanStrategiqueEp $psp) {}

    public function headings(): array
    {
        return [
            'Sous-Programme',
            'Programme de rattachement',
            'Objectif',
            'Indicateurs',
            'Stratégie',
            'Cadre institutionnel',
            'Responsable',
        ];
    }

    public function collection()
    {
        return $this->psp->sousProgrammes->map(fn($sp) => [
            $sp->libelle,
            $sp->programmeBudgetaire ? "{$sp->programmeBudgetaire->code} - {$sp->programmeBudgetaire->libelle}" : '—',
            $sp->objectif,
            $sp->indicateurs->pluck('libelle')->implode(' | '),
            $sp->strategie,
            $sp->cadre_institutionnel,
            $sp->responsable?->name,
        ]);
    }
}
