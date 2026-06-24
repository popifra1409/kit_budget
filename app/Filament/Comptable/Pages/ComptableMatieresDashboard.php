<?php

namespace App\Filament\Comptable\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;

class ComptableMatieresDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Tableau de Bord';
    protected static ?string $title           = 'Tableau de Bord — Comptabilité Matières';
    // protected static ?string $navigationGroup = 'Comptabilité Matières';
    protected static ?int    $navigationSort  = 0;
    protected static string  $view            = 'filament.comptable.pages.comptable-matieres-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_module_comptable') ?? false;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Comptable\Widgets\StatsComptableWidget::class,
            \App\Filament\Comptable\Widgets\AlertesStockWidget::class,
            \App\Filament\Comptable\Widgets\PipelineExpressionsBesoinWidget::class,
            \App\Filament\Comptable\Widgets\ActionsRequisesWidget::class,
            \App\Filament\Comptable\Widgets\MouvementsStockWidget::class,
            \App\Filament\Comptable\Widgets\TopArticlesConsommesWidget::class,
        ];
    }
}
