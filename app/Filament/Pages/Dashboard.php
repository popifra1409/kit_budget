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

        // Widgets pour super_admin
        if ($user->hasRole('super_admin')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
                \App\Filament\Widgets\CacheManagementWidget::class,
                \App\Filament\Widgets\ActivitesRecentesWidget::class,
            ];
        }

        // Widgets pour chef de service
        if ($user->hasRole('admin')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
                \App\Filament\Widgets\CacheManagementWidget::class,
                \App\Filament\Widgets\ActivitesRecentesWidget::class,
            ];
        }

        // Widgets pour chef de service
        if ($user->hasRole('directeur_general')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('daaf')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('chef_service_budget')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('operateur_budget')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('sous_directeur_budget')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('controleur_financier')) {
            return [
                \App\Filament\Widgets\ExerciceActifWidget::class,
                \App\Filament\Widgets\BudgetOverviewWidget::class,
                \App\Filament\Widgets\AlertesWidget::class,
                \App\Filament\Widgets\TauxRealisationWidget::class,
                \App\Filament\Widgets\GraphiqueEvolution::class,
                \App\Filament\Widgets\EvolutionMensuelleWidget::class,
                \App\Filament\Widgets\EngagementsParTypeWidget::class,
            ];
        }

        // Widgets pour opérateur
        if ($user->hasRole('agence_comptable')) {
            return [
                \App\Filament\Widgets\StatsRecettesOverview::class,
                \App\Filament\Widgets\TableRecettesMensuelles::class,
                \App\Filament\Widgets\RecettesStats::class,
                \App\Filament\Widgets\ChartRecettesMensuelles::class,
            ];
        }


        // Widgets par défaut (pour tous les autres rôles)
        return [
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
