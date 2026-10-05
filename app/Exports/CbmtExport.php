<?php

namespace App\Exports;

use App\Models\CbmtExercice;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Export Excel du CBMT seul : ressources et dépenses par titres, équilibre. */
class CbmtExport implements WithMultipleSheets
{
    public function __construct(protected CbmtExercice $cbmt) {}

    public function sheets(): array
    {
        return [
            new CbmtTableauSheet($this->cbmt, 'ressource'),
            new CbmtTableauSheet($this->cbmt, 'depense'),
            new CbmtEquilibreSheet($this->cbmt),
        ];
    }
}
