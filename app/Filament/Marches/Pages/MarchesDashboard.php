<?php

namespace App\Filament\Marches\Pages;

use Filament\Pages\Dashboard;

/**
 * Accueil du module. Sans page d'accueil déclarée, /marches renvoie un 404 :
 * le panneau n'a pas de page par défaut tant que discoverPages ne trouve rien.
 * Le tableau de bord métier (scans du 27/10) sera branché ici aux étapes suivantes.
 */
class MarchesDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Tableau de bord';
    protected static ?string $title = 'Tableau de bord — Marchés publics';
    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_module_marches') ?? false;
    }
}
