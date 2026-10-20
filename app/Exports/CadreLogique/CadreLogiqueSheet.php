<?php

namespace App\Exports\CadreLogique;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CadreLogiqueSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    /**
     * @param array $blocs Blocs produits par CadreLogiqueSousProgrammes (titre, objectif, responsable, lignes)
     * @param int[] $annees  [N-1, N, N+1, N+2, N+3]
     */
    public function __construct(
        protected string $titre,
        protected string $premiereColonne,
        protected array $blocs,
        protected array $annees,
    ) {}

    public function title(): string
    {
        return $this->titre;
    }

    public function headings(): array
    {
        [$realisation, $estimation, $cible1, $cible2, $cible3] = $this->annees;

        return [
            $this->premiereColonne,
            'Objectifs',
            'Indicateurs',
            'Unité de mesure de l\'indicateur',
            'Source de vérification',
            "Réalisation {$realisation}",
            "Estimation {$estimation}",
            "Valeur cible {$cible1}",
            "Valeur cible {$cible2}",
            "Valeur cible {$cible3}",
            'Responsable de mise en œuvre',
        ];
    }

    public function collection()
    {
        return collect($this->blocs)->flatMap(function (array $bloc) {
            if ($bloc['lignes'] === []) {
                return [[
                    $bloc['titre'],
                    $bloc['objectif'],
                    'Aucun indicateur défini',
                    null, null, null, null, null, null, null,
                    $bloc['responsable'],
                ]];
            }

            return collect($bloc['lignes'])->map(fn(array $ligne) => [
                $bloc['titre'],
                $bloc['objectif'],
                $ligne['indicateur'],
                $ligne['unite'],
                $ligne['source'],
                ...array_map(fn(int $annee) => $ligne['valeurs'][$annee], $this->annees),
                $bloc['responsable'],
            ])->all();
        });
    }
}
