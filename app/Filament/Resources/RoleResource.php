<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Rôles';

    protected static ?string $modelLabel = 'Rôle';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;


    /**
     * Permissions - Gestion des rôles
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_role') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_role') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_role') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_role') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_role') ?? false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du rôle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom du rôle')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Nom technique du rôle (ex: controleur_financier)'),

                        Forms\Components\TextInput::make('guard_name')
                            ->label('Guard')
                            ->default('web')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permissions du rôle')
                            ->relationship('permissions', 'name')
                            ->columns(3)
                            ->gridDirection('row')
                            ->searchable()
                            ->bulkToggleable()
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                self::formatPermissionLabel($record->name)
                            )
                            ->helperText('Sélectionnez les permissions pour ce rôle'),
                    ]),

                Forms\Components\Section::make('Statistiques')
                    ->schema([
                        Forms\Components\Placeholder::make('users_count')
                            ->label('Utilisateurs avec ce rôle')
                            ->content(
                                fn(?Role $record): string =>
                                $record?->users()->count() ?? 0
                            ),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Créé le')
                            ->content(
                                fn(?Role $record): string =>
                                $record?->created_at?->format('d/m/Y à H:i') ?? '-'
                            ),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Modifié le')
                            ->content(
                                fn(?Role $record): string =>
                                $record?->updated_at?->format('d/m/Y à H:i') ?? '-'
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
                    ->label('Rôle')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
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
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'danger',
                        'operateur_budget' => 'primary',
                        'chef_service_budget' => 'info',
                        'sous_directeur_budget' => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('success')
                    ->sortable(false), // ← CORRECTION PostgreSQL

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->badge()
                    ->color('info')
                    ->sortable(false), // ← CORRECTION PostgreSQL

                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn() =>
                        auth()->user()->hasAnyRole([
                            'chef_service_budget',
                            'sous_directeur_budget',
                            'daaf',
                        ])
                    ),
                // Tables\Actions\DeleteAction::make()
                //     ->requiresConfirmation()
                //     ->before(function (Role $record) {
                //         if ($record->users()->count() > 0) {
                //             throw new \Exception('Impossible de supprimer un rôle assigné à des utilisateurs');
                //         }
                //     }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
            'view' => Pages\ViewRole::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    /**
     * Formater le libellé des permissions
     */
    public static function formatPermissionLabel(string $permission): string
    {
        return match ($permission) {
            // Bordereaux
            'view_bordereau' => '👁️ Voir bordereau',
            'view_any_bordereau' => '👁️ Voir tous bordereaux',
            'create_bordereau' => '➕ Créer bordereau',
            'update_bordereau' => '✏️ Modifier bordereau',
            'delete_bordereau' => '🗑️ Supprimer bordereau',

            // Actions workflow
            'soumettre_bordereau' => '📤 Soumettre bordereau',
            'transmettre_bordereau' => '📨 Transmettre bordereau',
            'receptionner_bordereau' => '📥 Réceptionner bordereau',
            'valider_bordereau' => '✅ Valider bordereau',
            'rejeter_bordereau' => '❌ Rejeter bordereau',
            'retourner_bordereau' => '↩️ Retourner bordereau',
            'cloturer_bordereau' => '🔒 Clôturer bordereau',

            // Engagements
            'valider_engagement' => '✅ Valider engagement',
            'rejeter_engagement' => '❌ Rejeter engagement',

            // Vues
            'view_bordereaux_attente' => '⏳ Voir bordereaux en attente',
            'view_bordereaux_service' => '🏢 Voir bordereaux du service',
            'view_all_bordereaux' => '🌐 Voir tous bordereaux',

            // Stats
            'view_stats_workflow' => '📊 Voir statistiques',

            default => ucfirst(str_replace('_', ' ', $permission))
        };
    }
}
