<?php

namespace App\Filament\Widgets;

use App\Models\Exercice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsMultiExercicesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $exercices = Exercice::orderBy('annee', 'desc')->limit(3)->get();

        if ($exercices->isEmpty()) {
            return [
                Stat::make('Aucun exercice', 'Créez votre premier exercice')
                    ->description('Aller dans Configuration > Exercices')
                    ->descriptionIcon('heroicon-o-plus-circle')
                    ->color('gray'),
            ];
        }

        $stats = [];

        foreach ($exercices as $exercice) {
            // Calculer les statistiques si nécessaire
            if (empty($exercice->statistiques)) {
                $exercice->mettreAJourStatistiques();
                $exercice->refresh();
            }

            $statsEx = $exercice->statistiques;

            // Stat principale
            $stats[] = Stat::make("Exercice {$exercice->annee}", $exercice->getBadgeStatut())
                ->description($exercice->libelle)
                ->descriptionIcon('heroicon-o-calendar')
                ->color($exercice->getCouleurStatut())
                ->chart($this->getChartData($exercice))
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('open-modal', { id: 'exercice-{$exercice->id}' })"
                ]);

            // Programmes
            $stats[] = Stat::make("Programmes {$exercice->annee}", $statsEx['nb_programmes'] ?? 0)
                ->description('Programmes et sous-programmes')
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('primary');

            // Budget total AE
            $montantAE = $statsEx['montant_total_ae'] ?? 0;
            $stats[] = Stat::make("AE {$exercice->annee}", number_format($montantAE, 0, ',', ' ') . ' FCFA')
                ->description('Autorisations d\'engagement')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success');

            // Budget total CP
            $montantCP = $statsEx['montant_total_cp'] ?? 0;
            $stats[] = Stat::make("CP {$exercice->annee}", number_format($montantCP, 0, ',', ' ') . ' FCFA')
                ->description('Crédits de paiement')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning');
        }

        return $stats;
    }

    /**
     * Générer des données de graphique simple
     */
    protected function getChartData(Exercice $exercice): array
    {
        $stats = $exercice->statistiques;

        return [
            $stats['nb_programmes'] ?? 0,
            $stats['nb_budgets'] ?? 0,
            $stats['nb_bordereaux'] ?? 0,
        ];
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
