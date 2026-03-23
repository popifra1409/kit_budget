<?php

namespace App\Filament\Budget\Widgets;

use App\Models\Exercice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExerciceActifWidget extends BaseWidget
{
    protected static ?int $sort = -1; // Afficher en premier

    protected function getStats(): array
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return [
                Stat::make('Exercice actif', 'Aucun')
                    ->description('Aucun exercice actif')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        $stats = $exerciceActif->calculerStatistiques();

        return [
            Stat::make('Exercice actif', $exerciceActif->annee)
                ->description($exerciceActif->libelle)
                ->descriptionIcon('heroicon-o-calendar')
                ->color('success'),

            Stat::make('Programmes', $stats['nb_programmes'] ?? 0)
                ->description('Programmes et sous-programmes')
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('primary'),

            Stat::make('Budgets', $stats['nb_budgets'] ?? 0)
                ->description('Budgets créés')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Bordereaux', $stats['nb_bordereaux'] ?? 0)
                ->description('Bordereaux d\'engagement')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('info'),
        ];
    }
}
