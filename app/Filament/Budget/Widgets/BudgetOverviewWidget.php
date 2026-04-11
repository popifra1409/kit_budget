<?php

namespace App\Filament\Budget\Widgets;

use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BudgetOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = StatistiquesBudgetaires::getVueEnsemble();

        return [
            Stat::make('Budget Initial', StatistiquesBudgetaires::formatMontant($stats['budget_initial']))
                ->description("Exercice {$stats['exercice']}")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->chart([0, $stats['budget_initial']]),

            Stat::make('Virements', StatistiquesBudgetaires::formatMontant($stats['virements']))
                ->description($stats['virements'] >= 0 ? 'Augmentation' : 'Diminution')
                ->descriptionIcon($stats['virements'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['virements'] >= 0 ? 'success' : 'danger'),

            Stat::make('Budget Actualisé', StatistiquesBudgetaires::formatMontant($stats['budget_actualise']))
                ->description('Budget total disponible')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->chart([$stats['budget_initial'], $stats['budget_actualise']]),
        ];
    }
}
