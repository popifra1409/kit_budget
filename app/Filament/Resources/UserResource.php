<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Utilisateurs';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            if ($record->hasRole('super_admin')) {
                return false;
            }
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            if ($record->hasRole('super_admin')) {
                return false;
            }
            return true;
        }

        return false;
    }

    public static function canView($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            if ($record->hasRole('super_admin')) {
                return false;
            }
            return true;
        }

        return false;
    }

    // ========================================
    // QUERY
    // ========================================

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super_admin');
            });
        }

        return $query;
    }

    // ========================================
    // FORMULAIRE
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations personnelles')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->required(fn($record) => $record === null)
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText('Minimum 8 caractères. Laisser vide pour ne pas changer.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Rôles et Permissions')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Rôle')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(function () {
                                if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                    return Role::where('name', '!=', 'super_admin')
                                        ->pluck('name', 'id');
                                }
                                return Role::pluck('name', 'id');
                            })
                            ->helperText(function () {
                                if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                    return '⚠️ Le rôle Super Admin est réservé et non disponible';
                                }
                                return 'Sélectionnez un ou plusieurs rôles';
                            })
                            ->required(),
                    ]),

                Forms\Components\Section::make('Statut du Compte')
                    ->description('Activer ou désactiver l\'accès de l\'utilisateur à l\'application')
                    ->schema([
                        Forms\Components\Toggle::make('actif')
                            ->label('Compte actif')
                            ->default(true)
                            ->inline(false)
                            ->helperText(function ($record) {
                                // Message spécial pour super_admin
                                if ($record && $record->hasRole('super_admin')) {
                                    return '🔒 Les comptes Super Admin sont toujours actifs et ne peuvent pas être désactivés';
                                }
                                return 'Un utilisateur inactif ne peut pas se connecter';
                            })
                            ->disabled(function ($record) {
                                // Super admin ne peut jamais être désactivé
                                return $record && $record->hasRole('super_admin');
                            })
                            ->dehydrated(function ($record) {
                                // Si super_admin, forcer actif = true
                                if ($record && $record->hasRole('super_admin')) {
                                    return false; // Ne pas sauvegarder (garder true)
                                }
                                return true;
                            })
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copié')
                    ->copyMessageDuration(1500),

                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Rôle(s)')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'success' => fn($state) => in_array($state, ['directeur_general', 'daaf']),
                        'info' => fn($state) => in_array($state, ['chef_service_budget', 'sous_directeur_budget']),
                        'primary' => fn($state) => in_array($state, ['controleur_financier', 'agence_comptable']),
                        'gray' => 'operateur_budget',
                    ])
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        $translations = [
                            'super_admin' => 'Super Admin',
                            'admin' => 'Admin',
                            'directeur_general' => 'Directeur Général',
                            'daaf' => 'DAAF',
                            'sous_directeur_budget' => 'Sous-Directeur Budget',
                            'chef_service_budget' => 'Chef Service Budget',
                            'operateur_budget' => 'Opérateur Budget',
                            'controleur_financier' => 'Contrôleur Financier',
                            'agence_comptable' => 'Agence Comptable',
                        ];

                        return $translations[$state] ?? ucfirst(str_replace('_', ' ', $state));
                    }),

                Tables\Columns\ToggleColumn::make('actif')
                    ->label('Actif')
                    ->sortable()
                    ->disabled(function ($record) {
                        // ⭐ PROTECTION : Super admin ne peut jamais être désactivé
                        return $record->hasRole('super_admin');
                    })
                    ->beforeStateUpdated(function ($record, $state) {
                        // ⭐ PROTECTION DOUBLE : Vérification avant modification
                        if ($record->hasRole('super_admin')) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Action impossible')
                                ->body('🔒 Les comptes Super Admin doivent toujours rester actifs pour garantir l\'accès à l\'application.')
                                ->persistent()
                                ->send();

                            return false;
                        }

                        // Empêcher la désactivation de son propre compte
                        if ($record->id === auth()->id() && !$state) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Action impossible')
                                ->body('Vous ne pouvez pas désactiver votre propre compte.')
                                ->send();

                            return false;
                        }

                        // Empêcher admin de désactiver super_admin
                        if (
                            auth()->user()->hasRole('admin') &&
                            !auth()->user()->hasRole('super_admin') &&
                            $record->hasRole('super_admin')
                        ) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Action interdite')
                                ->body('Vous ne pouvez pas modifier un compte Super Admin.')
                                ->send();

                            return false;
                        }
                    })
                    ->afterStateUpdated(function ($record, $state) {
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Statut mis à jour')
                            ->body($state ?
                                '✅ L\'utilisateur peut maintenant se connecter.' :
                                '⚠️ L\'utilisateur ne peut plus se connecter. Il devra contacter un administrateur.')
                            ->send();
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement')
                    ->default(null),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Action rapide pour activer/désactiver
                    Tables\Actions\Action::make('toggle_actif')
                        ->label(fn($record) => $record->actif ? 'Désactiver' : 'Activer')
                        ->icon(fn($record) => $record->actif ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn($record) => $record->actif ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => $record->actif ? 'Désactiver cet utilisateur ?' : 'Activer cet utilisateur ?')
                        ->modalDescription(fn($record) => $record->actif ?
                            'L\'utilisateur ne pourra plus se connecter. Il devra contacter un administrateur pour réactiver son compte.' :
                            'L\'utilisateur pourra se connecter à l\'application.')
                        ->action(function ($record) {
                            // ⭐ PROTECTION : Super admin ne peut jamais être désactivé
                            if ($record->hasRole('super_admin')) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Action impossible')
                                    ->body('🔒 Les comptes Super Admin doivent toujours rester actifs.')
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            // Empêcher la désactivation de son propre compte
                            if ($record->id === auth()->id()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Action impossible')
                                    ->body('Vous ne pouvez pas désactiver votre propre compte.')
                                    ->send();
                                return;
                            }

                            // Empêcher admin de modifier super_admin
                            if (
                                auth()->user()->hasRole('admin') &&
                                !auth()->user()->hasRole('super_admin') &&
                                $record->hasRole('super_admin')
                            ) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Action interdite')
                                    ->body('Vous ne pouvez pas modifier un compte Super Admin.')
                                    ->send();
                                return;
                            }

                            $record->actif = !$record->actif;
                            $record->save();

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Statut modifié')
                                ->body($record->actif ?
                                    'Utilisateur activé avec succès.' :
                                    'Utilisateur désactivé. Il devra contacter un administrateur.')
                                ->send();
                        })
                        ->visible(
                            fn($record) =>
                            // Masquer pour super_admin
                            !$record->hasRole('super_admin') &&
                                // Masquer pour son propre compte
                                $record->id !== auth()->id() &&
                                // Masquer pour super_admin si on est admin
                                !(auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin'))
                        ),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                $hasSuperAdmin = $records->filter(function ($record) {
                                    return $record->hasRole('super_admin');
                                })->isNotEmpty();

                                if ($hasSuperAdmin) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Action interdite')
                                        ->body('Vous ne pouvez pas supprimer des utilisateurs Super Admin.')
                                        ->send();

                                    throw new \Exception('Vous ne pouvez pas supprimer des utilisateurs Super Admin.');
                                }
                            }
                        }),

                    // Action en masse pour activer
                    Tables\Actions\BulkAction::make('activer')
                        ->label('Activer la sélection')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            $skippedSuperAdmin = 0;

                            foreach ($records as $record) {
                                // ⭐ Ignorer les super_admin (déjà actifs)
                                if ($record->hasRole('super_admin')) {
                                    $skippedSuperAdmin++;
                                    continue;
                                }

                                if (
                                    auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin')
                                ) {
                                    continue;
                                }

                                $record->update(['actif' => true]);
                                $count++;
                            }

                            $message = $count . ' utilisateur(s) activé(s).';
                            if ($skippedSuperAdmin > 0) {
                                $message .= ' (' . $skippedSuperAdmin . ' Super Admin déjà actif(s))';
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Utilisateurs activés')
                                ->body($message)
                                ->send();
                        }),

                    // Action en masse pour désactiver
                    Tables\Actions\BulkAction::make('desactiver')
                        ->label('Désactiver la sélection')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Les utilisateurs ne pourront plus se connecter. Ils devront contacter un administrateur.')
                        ->action(function ($records) {
                            $count = 0;
                            $skippedSuperAdmin = 0;
                            $skippedSelf = 0;

                            foreach ($records as $record) {
                                // ⭐ PROTECTION : Ne jamais désactiver super_admin
                                if ($record->hasRole('super_admin')) {
                                    $skippedSuperAdmin++;
                                    continue;
                                }

                                // Ne pas désactiver son propre compte
                                if ($record->id === auth()->id()) {
                                    $skippedSelf++;
                                    continue;
                                }

                                if (
                                    auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin')
                                ) {
                                    continue;
                                }

                                $record->update(['actif' => false]);
                                $count++;
                            }

                            $message = $count . ' utilisateur(s) désactivé(s).';
                            if ($skippedSuperAdmin > 0) {
                                $message .= ' ⚠️ ' . $skippedSuperAdmin . ' Super Admin ignoré(s) (protection).';
                            }
                            if ($skippedSelf > 0) {
                                $message .= ' ⚠️ Votre propre compte ignoré.';
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Utilisateurs désactivés')
                                ->body($message)
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery()->where('actif', true);
        return (string) $query->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
