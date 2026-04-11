<?php

namespace App\Filament\Resources\RoleResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRoles = Role::count();
        $totalUsers = User::count();
        $rolesWithUsers = Role::has('users')->count();
        $rolesWithoutUsers = Role::doesntHave('users')->count();

        return [
            Stat::make('Total des rôles', $totalRoles)
                ->description('Rôles créés dans le système')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Rôles actifs', $rolesWithUsers)
                ->description('Rôles assignés à des utilisateurs')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Rôles inactifs', $rolesWithoutUsers)
                ->description('Rôles sans utilisateurs')
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->color('warning'),

            Stat::make('Utilisateurs total', $totalUsers)
                ->description('Utilisateurs dans le système')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
        ];
    }
}
