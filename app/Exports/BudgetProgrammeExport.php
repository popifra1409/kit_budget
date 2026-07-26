<?php

namespace App\Exports;

use App\Services\BudgetProgrammeService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BudgetProgrammeExport implements WithMultipleSheets
{
    public function __construct(
        private int    $anneeRef,
        private string $titre = 'Budget Programme',
        private string $structure = 'CHUY',
    ) {}

    public function sheets(): array
    {
        return [
            new BudgetProgrammeDepensesSheet($this->anneeRef, $this->titre, $this->structure),
            new BudgetProgrammeRecettesSheet($this->anneeRef, $this->titre, $this->structure),
        ];
    }
}
