<?php

namespace App\Exports\CadreLogique;

use App\Services\Planification\CadreLogiqueSousProgrammes;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CadreLogiqueCdmtExport implements WithMultipleSheets
{
    public function __construct(protected CadreLogiqueSousProgrammes $rapport) {}

    public function sheets(): array
    {
        $annees = $this->rapport->annees();

        $sheets = [];

        if (! $this->rapport->filtreUnSousProgramme()) {
            $sheets[] = new CadreLogiqueSheet(
                'Cadre stratégique',
                'Sous-programmes',
                $this->rapport->cadreStrategique()->all(),
                $annees,
            );
        }

        foreach ($this->rapport->matrices() as $matrice) {
            $sp = $matrice['sous_programme'];
            $blocs = array_merge([$matrice['bloc']], $matrice['actions']->all());

            $sheets[] = new CadreLogiqueSheet(
                $this->titreOnglet($sp->code, $sp->libelle),
                'Sous-programme/Actions',
                $blocs,
                $annees,
            );
        }

        return $sheets;
    }

    /** Les noms d'onglets Excel sont limités à 31 caractères et à certains signes. */
    protected function titreOnglet(string $code, string $libelle): string
    {
        $titre = preg_replace('/[\[\]:*?\/\\\\]/', '-', "{$code} {$libelle}");

        return trim(mb_substr($titre, 0, 31));
    }
}
