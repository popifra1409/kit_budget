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
            'Tableau 9 - Ressources' => new CbmtTableauSheet($this->cdmt, 'ressource'),
            'Tableau 10 - Dépenses' => new CbmtTableauSheet($this->cdmt, 'depense'),
            'Annexe C - Activités' => new CdmtAnnexeCSheet($this->cdmt),
        ];
    }
}
