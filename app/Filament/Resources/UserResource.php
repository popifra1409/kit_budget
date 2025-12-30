<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canEdit($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

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
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->revealable()
                            ->helperText(
                                fn(string $context): string =>
                                $context === 'edit'
                                    ? 'Laissez vide pour conserver le mot de passe actuel'
                                    : 'Minimum 8 caractères recommandés'
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Rôles et permissions')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Rôles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                match ($record->name) {
                                    'super_admin' => '🔴 Super Admin',
                                    'operateur_budget' => '👤 Opérateur Budget',
                                    'chef_service_budget' => '👨‍💼 Chef Service Budget',
                                    'sous_directeur_budget' => '👔 Sous-Directeur Budget',
                                    'directeur_general' => '🎯 Directeur Général',
                                    'controleur_financier' => '💼 Contrôleur Financier',
                                    'agence_comptable' => '💰 Agence Comptable',
                                    default => $record->name
                                }
                            )
                            ->helperText('Sélectionnez un ou plusieurs rôles pour cet utilisateur'),

                        Forms\Components\Placeholder::make('permissions_info')
                            ->label('Permissions')
                            ->content('Les permissions sont héritées des rôles sélectionnés')
                            ->visible(fn($record) => $record !== null),
                    ]),

                Forms\Components\Section::make('Statistiques')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Créé le')
                            ->content(
                                fn(?User $record): string =>
                                $record?->created_at?->format('d/m/Y à H:i') ?? '-'
                            ),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Modifié le')
                            ->content(
                                fn(?User $record): string =>
                                $record?->updated_at?->format('d/m/Y à H:i') ?? '-'
                            ),

                        Forms\Components\Placeholder::make('bordereaux_count')
                            ->label('Bordereaux émis')
                            ->content(
                                fn(?User $record): string =>
                                $record?->bordereauxEmis()->count() ?? 0
                            ),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record !== null),
            ]);
    }

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
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copié')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rôles')
                    ->badge()
                    ->colors([
                        'danger' => 'super_admin',
                        'primary' => 'operateur_budget',
                        'info' => 'chef_service_budget',
                        'warning' => 'sous_directeur_budget',
                        'success' => fn($state) => in_array($state, ['directeur_general', 'controleur_financier', 'agence_comptable']),
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'super_admin' => 'Super Admin',
                        'operateur_budget' => 'Opérateur',
                        'chef_service_budget' => 'Chef Service',
                        'sous_directeur_budget' => 'Sous-Directeur',
                        'directeur_general' => 'Directeur',
                        'controleur_financier' => 'Contrôleur',
                        'agence_comptable' => 'Comptable',
                        default => $state
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('bordereaux_emis_count')
                    ->label('Bordereaux')
                    ->counts('bordereauxEmis')
                    ->badge()
                    ->color('success')
                    ->sortable(false), // ← DÉSACTIVÉ pour éviter l'erreur PostgreSQL

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
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
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
