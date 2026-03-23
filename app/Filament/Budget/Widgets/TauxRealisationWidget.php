<?php

namespace App\Filament\Budget\Widgets;

use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TauxRealisationWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $stats = StatistiquesBudgetaires::getVueEnsemble();

        $taux = $stats['taux_execution'];

        // Déterminer la couleur selon le taux
        $color = match (true) {
            $taux < 50 => 'success',
            $taux < 75 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Taux de Réalisation', StatistiquesBudgetaires::formatPourcentage($taux))
                ->description('Engagements / Budget actualisé')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($color)
                ->chart(array_fill(0, 12, $taux)),

            Stat::make('Engagé', StatistiquesBudgetaires::formatMontant($stats['engage']))
                ->description(StatistiquesBudgetaires::formatPourcentage($taux) . ' du budget')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('warning'),

            Stat::make('Disponible', StatistiquesBudgetaires::formatMontant($stats['disponible']))
                ->description(StatistiquesBudgetaires::formatPourcentage(100 - $taux) . ' restant')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
