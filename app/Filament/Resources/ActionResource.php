<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActionResource\Pages;
use App\Models\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActionResource extends Resource
{
    protected static ?string $model = Action::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Actions';

    protected static ?string $modelLabel = 'Action';

    protected static ?string $pluralModelLabel = 'Actions';

    protected static ?string $navigationGroup = 'Cadre Logique';

    protected static ?int $navigationSort = 2;

    /**
     * Permissions - Gestion quotidienne par OB/CS
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations de l\'Action')
                    ->schema([
                        Forms\Components\Select::make('programme_id')
                            ->label('Programme')
                            ->relationship('programme', 'libelle')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->code} - {$record->libelle}"),

                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Ex: A4'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Prise en charge des maladies chroniques'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objectif Spécifique')
                    ->description('Définissez l\'objectif spécifique de cette action')
                    ->schema([
                        Forms\Components\Repeater::make('objectifsSpecifiques')
                            ->relationship('objectifsSpecifiques')
                            ->label('')
                            ->schema([
                                Forms\Components\Textarea::make('libelle')
                                    ->label('Objectif spécifique')
                                    ->required()
                                    ->rows(2)
                                    ->placeholder('Ex: Améliorer la prise en charge des cas chroniques...'),

                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columnSpanFull()
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter un objectif spécifique')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => \Str::limit($state['libelle'] ?? 'Nouvel objectif', 50)),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('programme.code')
                    ->label('Programme')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('activites_count')
                    ->label('Activités')
                    ->counts('activites')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('budget_total')
                    ->label('Budget (AE)')
                    ->formatStateUsing(fn($record) => number_format($record->getBudgetTotal(), 0, ',', ' ') . ' FCFA')
                    ->color('warning'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('programme_id')
                    ->label('Programme')
                    ->relationship('programme', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
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
            'index' => Pages\ListActions::route('/'),
            'create' => Pages\CreateAction::route('/create'),
            'edit' => Pages\EditAction::route('/{record}/edit'),
        ];
    }
}
