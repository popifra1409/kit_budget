<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Modifier'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informations de la permission')
                    ->schema([
                        Components\TextEntry::make('name')
                            ->label('Nom de la permission')
                            ->formatStateUsing(
                                fn($state) =>
                                ucfirst(str_replace('_', ' ', $state))
                            )
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->badge()
                            ->color('primary'),

                        Components\TextEntry::make('guard_name')
                            ->label('Guard')
                            ->badge()
                            ->color('secondary'),
                    ])
                    ->columns(2),

                Components\Section::make('Rôles utilisant cette permission')
                    ->schema([
                        Components\TextEntry::make('roles.name')
                            ->label('Rôles')
                            ->listWithLineBreaks()
                            ->bulleted()
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
                            ->placeholder('Aucun rôle n\'utilise cette permission'),

                        Components\TextEntry::make('roles_count')
                            ->label('Nombre de rôles')
                            ->state(function ($record) {
                                return $record->roles()->count();
                            })
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-shield-check'),
                    ])
                    ->columns(2),

                Components\Section::make('Utilisateurs (via rôles)')
                    ->schema([
                        Components\TextEntry::make('users_count')
                            ->label('Nombre d\'utilisateurs')
                            ->state(function ($record) {
                                return $record->users()->count();
                            })
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-users')
                            ->helperText('Utilisateurs ayant cette permission via leurs rôles'), // ← helperText au lieu de description
                    ]),

                Components\Section::make('Métadonnées')
                    ->schema([
                        Components\TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar'),

                        Components\TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar')
                            ->since(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
