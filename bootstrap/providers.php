<?php

return [
    App\Providers\AppServiceProvider::class,

    // Panels Filament — le PORTAIL est le seul panel par défaut
    App\Providers\Filament\PortalPanelProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\BudgetPanelProvider::class,
    App\Providers\Filament\ComptablePanelProvider::class,
    App\Providers\Filament\MarchesPanelProvider::class,
    App\Providers\Filament\PlanificationPanelProvider::class,
    App\Providers\Filament\ProgrammationPanelProvider::class,
    App\Providers\Filament\SuiviEvaluationPanelProvider::class,
];
