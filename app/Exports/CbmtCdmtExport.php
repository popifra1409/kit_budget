<?php

namespace App\Exports;

use App\Models\CbmtLigne;
use App\Models\CdmtExercice;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CbmtCdmtExport implements WithMultipleSheets
{
    public function __construct(protected CdmtExercice $cdmt) {}

    public function sheets(): array
    {
        return [
            'Ressources par titres' => new CbmtTableauSheet($this->cdmt, 'ressource'),
            'Dépenses par titres' => new CbmtTableauSheet($this->cdmt, 'depense'),
            'Annexe C - Activités' => new CdmtAnnexeCSheet($this->cdmt),
        ];
    }
}
