<?php

namespace App\Exports;

use App\Models\RapportActivitePeriodique;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RapportActivitePeriodiqueExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(protected RapportActivitePeriodique $rapport) {}

    public function title(): string
    {
        return 'Annexe 9 - ' . $this->rapport->periode;
    }

    public function headings(): array
    {
        return ['Nature', 'Libellé', 'Unité', 'Prévision', 'Réalisation', 'Écart', 'Taux (%)'];
    }

    public function collection()
    {
        $this->rapport->loadMissing('lignes');
        $rows = collect();

        foreach (['tache' => 'Tâche', 'moyen' => 'Moyen'] as $nature => $label) {
            foreach ($this->rapport->lignes->where('nature', $nature) as $l) {
                $rows->push([$label, $l->libelle, $l->unite, (float) $l->prevision, (float) $l->realisation, $l->ecart, $l->taux_realisation]);
            }
            $s = $this->rapport->getSynthese($nature);
            $rows->push(["TOTAL {$label}S", '', '', $s['prevision'], $s['realisation'], $s['ecart'], $s['taux']]);
        }

        return $rows;
    }
}
