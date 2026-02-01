<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Rôles';

    protected static ?string $modelLabel = 'Rôle';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;


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
        // Ne peut supprimer que si aucun utilisateur n'a ce rôle
        if (!auth()->user()?->can('delete_role')) {
            return false;
        }

        return $record->users()->count() === 0;
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
                            ->placeholder('Ex: operateur_budget')
                            ->helperText('Nom technique du rôle (sans espaces)'),

                        Forms\Components\TextInput::make('niveau_hierarchique')
                            ->label('Niveau hiérarchique')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('0 = plus bas niveau, 100 = plus haut niveau')
                            ->suffix('/100'),

                        Forms\Components\Placeholder::make('niveau_info')
                            ->label('Guide des niveaux')
                            ->content(function () {
                                return "• 10: Opérateurs (budget, recette)\n" .
                                    "• 30: Chefs de service\n" .
                                    "• 50: Sous-directeurs\n" .
                                    "• 60: Contrôleur financier, Agent comptable\n" .
                                    "• 70: DAAF\n" .
                                    "• 90: Directeur\n" .
                                    "• 100: Super administrateur";
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('permissions')
                            ->label('Permissions')
                            ->multiple()
                            ->relationship('permissions', 'name')
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
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

                Tables\Columns\TextColumn::make('niveau_hierarchique')
                    ->label('Niveau')
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->niveau_hierarchique >= 90 => 'danger',
                        $record->niveau_hierarchique >= 60 => 'warning',
                        $record->niveau_hierarchique >= 30 => 'primary',
                        default => 'success',
                    })
                    ->formatStateUsing(fn($state) => "Niveau {$state}"),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->badge()
                    ->color('success'),

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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->users()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('niveau_hierarchique', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
