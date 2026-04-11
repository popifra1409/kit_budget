<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Tous les utilisateurs connectés peuvent voir le dashboard
     * Pas besoin de permission spécifique
     */
    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected static bool $shouldRegisterNavigation = true;

    /**
     * Ordre dans la navigation
     */
    protected static ?int $navigationSort = -1;

    /**
     * Icône
     */
    protected static ?string $navigationIcon = 'heroicon-o-home';

    /**
     * Label
     */
    protected static ?string $navigationLabel = 'Tableau de Bord';

    /**
     * Titre de la page
     */
    public function getTitle(): string
    {
        return 'Tableau de Bord';
    }

    /**
     * Widgets du dashboard selon le rôle
     */
    public function getWidgets(): array
    {
        $user = auth()->user();

        // Widgets de workflow (prioritaires)
        $workflowWidgets = [
            \App\Filament\Budget\Widgets\StatistiquesTransmissionsWidget::class,
            \App\Filament\Budget\Widgets\MesTachesEnAttenteWidget::class,
        ];

        // Widgets de workflow admin
        $workflowAdminWidgets = [
            \App\Filament\Budget\Widgets\StatistiquesTransmissionsWidget::class,
            \App\Filament\Budget\Widgets\MesTachesEnAttenteWidget::class,
            \App\Filament\Budget\Widgets\ToutesLesTransmissionsWidget::class,
        ];

        $profilWidgets = [
            \App\Filament\Widgets\ChangerMotDePasseWidget::class,
        ];

        // Widgets budgétaires
        $budgetWidgets = [
            \App\Filament\Budget\Widgets\ExerciceActifWidget::class,
            \App\Filament\Budget\Widgets\BudgetOverviewWidget::class,
            \App\Filament\Budget\Widgets\AlertesWidget::class,
            \App\Filament\Budget\Widgets\TauxRealisationWidget::class,
        ];

        $advancedBudgetWidgets = [
            \App\Filament\Budget\Widgets\GraphiqueEvolution::class,
            \App\Filament\Budget\Widgets\EvolutionMensuelleWidget::class,
            \App\Filament\Budget\Widgets\EngagementsParTypeWidget::class,
        ];

        $recettesWidgets = [
            \App\Filament\Budget\Widgets\StatsRecettesOverview::class,
            \App\Filament\Budget\Widgets\TableRecettesMensuelles::class,
            \App\Filament\Budget\Widgets\RecettesStats::class,
            \App\Filament\Budget\Widgets\ChartRecettesMensuelles::class,
        ];

        $adminWidgets = [
            \App\Filament\Budget\Widgets\CacheManagementWidget::class,
            \App\Filament\Budget\Widgets\ActivitesRecentesWidget::class,
        ];

        // Super Admin
        if ($user->hasRole('super_admin')) {
            return array_merge(
                $workflowAdminWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets,
                $adminWidgets
            );
        }

        // Admin
        if ($user->hasRole('admin')) {
            return array_merge(
                $workflowAdminWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets,
                $adminWidgets
            );
        }

        // Directeur Général
        if ($user->hasRole('directeur_general')) {
            return array_merge(
                $workflowAdminWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets
            );
        }

        // DAAF
        if ($user->hasRole('daaf')) {
            return array_merge(
                $workflowAdminWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets
            );
        }

        // Chef Service Budget
        if ($user->hasRole('chef_service_budget')) {
            return array_merge(
                $workflowWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets
            );
        }

        // Sous-directeur Budget
        if ($user->hasRole('sous_directeur_budget')) {
            return array_merge(
                $workflowWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets,
                $recettesWidgets
            );
        }

        // Contrôleur Financier
        if ($user->hasRole('controleur_financier')) {
            return array_merge(
                $workflowAdminWidgets,
                $budgetWidgets,
                $advancedBudgetWidgets
            );
        }

        // Opérateur Budget
        if ($user->hasRole('operateur_budget')) {
            return array_merge(
                $workflowWidgets,
                $budgetWidgets
            );
        }

        // Agence Comptable
        if ($user->hasRole('agence_comptable')) {
            return array_merge(
                $workflowWidgets,
                $recettesWidgets
            );
        }

        // Widgets par défaut (pour tous les autres rôles)
        return [
            \App\Filament\Budget\Widgets\StatistiquesTransmissionsWidget::class,
            \App\Filament\Budget\Widgets\MesTachesEnAttenteWidget::class,
            \App\Filament\Widgets\WelcomeWidget::class,
        ];
    }

    /**
     * Colonnes du dashboard
     */
    public function getColumns(): int | string | array
    {
        return 2;
    }
}
