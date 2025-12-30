<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Modifier'),
            Actions\DeleteAction::make()
                ->label('Supprimer')
                ->requiresConfirmation()
                ->before(function ($record) {
                    if ($record->users()->count() > 0) {
                        throw new \Exception('Impossible de supprimer un rôle assigné à des utilisateurs');
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informations du rôle')
                    ->schema([
                        Components\TextEntry::make('name')
                            ->label('Nom du rôle')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'super_admin' => '🔴 Super Admin',
                                'operateur_budget' => '👤 Opérateur Budget',
                                'chef_service_budget' => '👨‍💼 Chef Service Budget',
                                'sous_directeur_budget' => '👔 Sous-Directeur Budget',
                                'directeur_general' => '🎯 Directeur Général',
                                'controleur_financier' => '💼 Contrôleur Financier',
                                'agence_comptable' => '💰 Agence Comptable',
                                default => $state
                            })
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'super_admin' => 'danger',
                                'operateur_budget' => 'primary',
                                'chef_service_budget' => 'info',
                                'sous_directeur_budget' => 'warning',
                                default => 'success',
                            }),

                        Components\TextEntry::make('guard_name')
                            ->label('Guard')
                            ->badge()
                            ->color('secondary'),
                    ])
                    ->columns(2),

                Components\Section::make('Permissions')
                    ->schema([
                        Components\TextEntry::make('permissions.name')
                            ->label('Permissions assignées')
                            ->badge()
                            ->formatStateUsing(
                                fn($state) =>
                                RoleResource::formatPermissionLabel($state)
                            )
                            ->color('success')
                            ->placeholder('Aucune permission'),

                        Components\TextEntry::make('permissions_count')
                            ->label('Nombre total de permissions')
                            ->state(function ($record) {
                                return $record->permissions()->count();
                            })
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-key'),
                    ]),

                Components\Section::make('Utilisateurs')
                    ->schema([
                        Components\TextEntry::make('users.name')
                            ->label('Utilisateurs avec ce rôle')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->icon('heroicon-o-user')
                            ->placeholder('Aucun utilisateur'),

                        Components\TextEntry::make('users_count')
                            ->label('Nombre total d\'utilisateurs')
                            ->state(function ($record) {
                                return $record->users()->count();
                            })
                            ->badge()
                            ->color('primary')
                            ->icon('heroicon-o-users'),
                    ])
                    ->columns(2),

                Components\Section::make('Métadonnées')
                    ->schema([
                        Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar'),

                        Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar')
                            ->since(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
