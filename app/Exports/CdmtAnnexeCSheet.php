<?php

namespace App\Exports;

use App\Models\CdmtExercice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CdmtAnnexeCSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected CdmtExercice $cdmt) {}

    public function headings(): array
    {
        return ['Sous-Programme', 'Action', 'Activité', 'Nature (LR/MN)', 'N+1 AE', 'N+1 CP', 'N+2 AE', 'N+2 CP', 'N+3 AE', 'N+3 CP'];
    }

    public function array(): array
    {
        return $this->cdmt->lignes->map(fn($l) => [
            $l->sousProgrammeEp?->libelle,
            $l->action?->libelle,
            $l->libelle,
            $l->nature,
            $l->n_plus_1_ae,
            $l->n_plus_1_cp,
            $l->n_plus_2_ae,
            $l->n_plus_2_cp,
            $l->n_plus_3_ae,
            $l->n_plus_3_cp,
        ])->toArray();
    }

    public function title(): string
    {
        return 'Annexe C';
    }
}
