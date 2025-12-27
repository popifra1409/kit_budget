<?php

namespace App\Filament\Widgets;

use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\ChartWidget;

class EngagementsParTypeWidget extends ChartWidget
{
    protected static ?string $heading = 'Engagements par Type';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $stats = StatistiquesBudgetaires::getEngagementsParType();

        $labels = [];
        $data = [];
        $colors = [
            'BC' => '#3b82f6',      // blue
            'DA' => '#10b981',      // green
            'prime' => '#10b981',   // green
            'mission' => '#f59e0b', // amber
            'avance' => '#06b6d4',  // cyan
            'formation' => '#f59e0b', // amber
            'default' => '#6b7280', // gray
        ];

        $backgroundColors = [];

        foreach ($stats as $stat) {
            $type = $stat['type'];
            $labels[] = ucfirst($type) . ' (' . $stat['nombre'] . ')';
            $data[] = $stat['montant'];
            $backgroundColors[] = $colors[$type] ?? $colors['default'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Montant (FCFA)',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
