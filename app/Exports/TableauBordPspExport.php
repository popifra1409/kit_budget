<?php

namespace App\Exports;

use App\Models\PlanStrategiqueEp;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TableauBordPspExport implements FromCollection, WithHeadings
{
    public function __construct(protected PlanStrategiqueEp $psp) {}

    public function headings(): array
    {
        return ['Exercice', 'N° PPA', 'Statut', 'AE prévu', 'CP prévu', 'Engagé (réel)', 'Disponible', 'Taux exécution (%)'];
    }

    public function collection()
    {
        return $this->psp->getSyntheseParExercice()->map(fn($row) => [
            $row['exercice'],
            $row['ppa_numero'],
            $row['statut_ppa'],
            $row['ae_prevu'],
            $row['cp_prevu'],
            $row['engage_reel'],
            $row['disponible'],
            $row['taux_execution'],
        ]);
    }
}
