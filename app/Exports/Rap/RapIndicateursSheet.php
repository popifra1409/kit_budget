<?php

namespace App\Exports\Rap;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RapIndicateursSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(protected Collection $etat) {}

    public function title(): string
    {
        return 'Indicateurs';
    }

    public function headings(): array
    {
        return ['Sous-programme', 'Indicateur', 'Référence', 'Cible', 'Réalisé', 'Période', "Taux d'atteinte (%)"];
    }

    public function collection()
    {
        return $this->etat->flatMap(fn($r) => $r['indicateurs']->map(fn($i) => [
            $r['sous_programme']->libelle,
            $i['libelle'],
            $i['reference'],
            $i['cible'],
            $i['realise'],
            $i['periode'],
            $i['taux'],
        ]));
    }
}
