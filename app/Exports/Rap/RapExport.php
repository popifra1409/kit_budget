<?php

namespace App\Exports\Rap;

use App\Models\RapportAnnuelPerformance;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RapExport implements WithMultipleSheets
{
    public function __construct(protected RapportAnnuelPerformance $rap) {}

    public function sheets(): array
    {
        $etat = $this->rap->getEtatMiseEnOeuvre(); // calcule une seule fois pour les 2 onglets

        return [
            new RapExecutionSheet($this->rap, $etat),
            new RapIndicateursSheet($etat),
        ];
    }
}
