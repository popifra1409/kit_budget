<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Modifier'),
            Actions\DeleteAction::make()
                ->label('Supprimer')
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informations personnelles')
                    ->schema([
                        Components\TextEntry::make('name')
                            ->label('Nom complet')
                            ->icon('heroicon-o-user')
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Components\TextEntry::make('email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->copyMessage('Email copié')
                            ->copyMessageDuration(1500),
                    ])
                    ->columns(2),

                Components\Section::make('Rôles et permissions')
                    ->schema([
                        Components\TextEntry::make('roles.name')
                            ->label('Rôles')
                            ->badge()
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
                            ->color(fn($state) => match ($state) {
                                'super_admin' => 'danger',
                                'operateur_budget' => 'primary',
                                'chef_service_budget' => 'info',
                                'sous_directeur_budget' => 'warning',
                                default => 'success',
                            })
                            ->placeholder('Aucun rôle assigné'),

                        Components\TextEntry::make('permissions_count')
                            ->label('Nombre de permissions')
                            ->state(function ($record) {
                                return $record->getAllPermissions()->count();
                            })
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-key'),
                    ])
                    ->columns(2),

                Components\Section::make('Activité')
                    ->schema([
                        Components\TextEntry::make('bordereauxEmis_count')
                            ->label('Bordereaux émis')
                            ->state(function ($record) {
                                return $record->bordereauxEmis()->count();
                            })
                            ->badge()
                            ->color('primary')
                            ->icon('heroicon-o-document-text'),

                        Components\TextEntry::make('bordereauxValides_count')
                            ->label('Bordereaux validés')
                            ->state(function ($record) {
                                return $record->bordereauxValides()->count();
                            })
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-check-circle'),

                        Components\TextEntry::make('bordereauxRejetes_count')
                            ->label('Bordereaux rejetés')
                            ->state(function ($record) {
                                return $record->bordereauxRejetes()->count();
                            })
                            ->badge()
                            ->color('danger')
                            ->icon('heroicon-o-x-circle'),

                        Components\TextEntry::make('bordereauxDetenus_count')
                            ->label('Bordereaux en cours')
                            ->state(function ($record) {
                                return $record->bordereauxDetenus()->count();
                            })
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(4),

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

                        Components\TextEntry::make('email_verified_at')
                            ->label('Email vérifié le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-shield-check')
                            ->placeholder('Non vérifié'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }
}
