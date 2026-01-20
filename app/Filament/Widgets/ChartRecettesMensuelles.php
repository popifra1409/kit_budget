<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\Exercice;

class ChartRecettesMensuelles extends ChartWidget
{
    protected static ?string $heading = 'Évolution Mensuelle des Recettes';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Récupérer toutes les prévisions mensuelles de l'exercice
        $previsions = PrevisionRecetteMensuelle::where('exercice_id', $exerciceActif->id)
            ->where('actif', true)
            ->orderBy('mois')
            ->get()
            ->groupBy('mois');

        $labels = [];
        $dataPrevues = [];
        $dataRecouvrées = [];

        $moisFr = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aoû',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc'
        ];

        for ($mois = 1; $mois <= 12; $mois++) {
            $labels[] = $moisFr[$mois];

            if (isset($previsions[$mois])) {
                $montantPrevu = $previsions[$mois]->sum('montant_prevu');
                $montantRecouvre = $previsions[$mois]->sum('montant_recouvre');
            } else {
                $montantPrevu = 0;
                $montantRecouvre = 0;
            }

            $dataPrevues[] = round($montantPrevu / 1000000, 2); // En millions
            $dataRecouvrées[] = round($montantRecouvre / 1000000, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prévu',
                    'data' => $dataPrevues,
                    'borderColor' => 'rgb(59, 130, 246)', // Blue
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Recouvré',
                    'data' => $dataRecouvrées,
                    'borderColor' => 'rgb(34, 197, 94)', // Green
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + 'M FCFA';
                        }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return value + 'M'; }",
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Montant (Millions FCFA)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Mois',
                    ],
                ],
            ],
        ];
    }
}
