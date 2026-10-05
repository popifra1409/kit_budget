<?php

namespace App\Exports;

use App\Models\CdmtExercice;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Export Excel CBMT + CDMT : ressources et dépenses par titres, équilibre, Annexe C. */
class CbmtCdmtExport implements WithMultipleSheets
{
    public function __construct(protected CdmtExercice $cdmt) {}

    public function sheets(): array
    {
        return [
            new CbmtTableauSheet($this->cdmt->cbmtExercice, 'ressource'),
            new CbmtTableauSheet($this->cdmt->cbmtExercice, 'depense'),
            new CbmtEquilibreSheet($this->cdmt->cbmtExercice),
            new CdmtAnnexeCSheet($this->cdmt),
        ];
    }
}
