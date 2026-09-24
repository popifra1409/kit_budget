<?php

namespace App\Exports\Rap;

use App\Models\RapportAnnuelPerformance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RapExecutionSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(protected RapportAnnuelPerformance $rap, protected Collection $etat) {}

    public function title(): string
    {
        return 'Exécution financière';
    }

    public function headings(): array
    {
        return ['Code', 'Sous-programme', 'Nb activités', 'CP prévus', 'Engagé', 'Disponible', 'Taux (%)'];
    }

    public function collection()
    {
        $rows = $this->etat->map(fn($r) => [
            $r['sous_programme']->programmeBudgetaire?->code ?? $r['sous_programme']->code,
            $r['sous_programme']->libelle,
            $r['nb_activites'],
            $r['cp_prevu'],
            $r['engage'],
            $r['disponible'],
            $r['taux_execution'],
        ]);

        $t = $this->rap->getTotauxExecution($this->etat);

        return $rows->push(['TOTAL', '', '', $t['cp_prevu'], $t['engage'], $t['disponible'], $t['taux_execution']]);
    }
}
