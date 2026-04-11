<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Créer un rôle')
                ->icon('heroicon-o-plus')
                ->modalHeading('Créer un nouveau rôle')
                ->modalWidth('2xl'),
        ];
    }

    /**
     * Onglets pour filtrer par niveau hiérarchique
     */
    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous les rôles')
                ->badge(Role::count())
                ->badgeColor('primary'),

            'direction' => Tab::make('Direction')
                ->icon('heroicon-o-user-group')
                ->badge(Role::where('niveau_hierarchique', '>=', 70)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('niveau_hierarchique', '>=', 70)
                ),

            'cadres' => Tab::make('Cadres & Responsables')
                ->icon('heroicon-o-briefcase')
                ->badge(Role::whereBetween('niveau_hierarchique', [30, 69])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereBetween('niveau_hierarchique', [30, 69])
                ),

            'operateurs' => Tab::make('Opérateurs')
                ->icon('heroicon-o-users')
                ->badge(Role::where('niveau_hierarchique', '<', 30)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('niveau_hierarchique', '<', 30)
                ),

            'sans_utilisateurs' => Tab::make('Sans utilisateurs')
                ->icon('heroicon-o-exclamation-circle')
                ->badge(Role::doesntHave('users')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->doesntHave('users')
                ),
        ];
    }

    /**
     * Widgets d'en-tête
     */
    protected function getHeaderWidgets(): array
    {
        return [
            RoleResource\Widgets\RoleStatsOverview::class,
        ];
    }

    /**
     * Messages de notification
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Rôle créé avec succès';
    }

    /**
     * Polling pour rafraîchir automatiquement
     */
    protected function getPollingInterval(): ?string
    {
        return null; // Désactivé par défaut, peut être '10s' pour rafraîchir toutes les 10s
    }
}
