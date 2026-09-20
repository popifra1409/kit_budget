<?php

namespace App\Exports;

use App\Models\PpaExercice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PpaExport implements FromCollection, WithHeadings
{
    public function __construct(protected PpaExercice $ppa) {}

    public function headings(): array
    {
        return ['Sous-Programme', 'Action', 'Activité', 'Indicateurs', 'AE prévu', 'CP prévu', 'Engagé (réel)', 'Disponible', 'Taux exécution (%)'];
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->ppa->getSousProgrammesAvecActivites() as $sp) {
            foreach ($sp->actions as $action) {
                foreach ($action->activites as $activite) {
                    $exec = $activite->getExecutionBudgetaire();
                    $rows->push([
                        $sp->libelle,
                        $action->libelle,
                        $activite->libelle,
                        $activite->indicateurs->pluck('libelle')->implode(' | '),
                        $activite->getTotalAe(),
                        $activite->getTotalCp(),
                        $exec['engage'],
                        $exec['disponible'],
                        $exec['taux_engagement'],
                    ]);
                }
            }
        }

        return $rows;
    }
}
