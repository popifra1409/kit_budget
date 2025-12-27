<?php

namespace App\Filament\Widgets;

use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\ChartWidget;

class EvolutionMensuelleWidget extends ChartWidget
{
    protected static ?string $heading = 'Évolution Mensuelle des Engagements';

    protected static ?int $sort = 6; // EN BAS

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $stats = StatistiquesBudgetaires::getEvolutionMensuelle();

        $labels = array_column($stats, 'mois');
        $data = array_column($stats, 'montant');

        return [
            'datasets' => [
                [
                    'label' => 'Engagements (FCFA)',
                    'data' => $data,
                    'fill' => true,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
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
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return new Intl.NumberFormat("fr-FR").format(value) + " FCFA"; }',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.dataset.label + ": " + new Intl.NumberFormat("fr-FR").format(context.parsed.y) + " FCFA"; }',
                    ],
                ],
            ],
        ];
    }
}
