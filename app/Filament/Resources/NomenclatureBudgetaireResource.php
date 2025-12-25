<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NomenclatureBudgetaireResource\Pages;
use App\Filament\Resources\NomenclatureBudgetaireResource\RelationManagers;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NomenclatureBudgetaireResource extends Resource
{
    protected static ?string $model = NomenclatureBudgetaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Nomenclature Budgétaire';

    protected static ?string $modelLabel = 'Nomenclature';

    protected static ?string $pluralModelLabel = 'Nomenclatures';

    protected static ?string $navigationGroup = 'Configuration Budget';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Ex: 601300, 722200, 112000'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Carburants et lubrifiants'),

                        Forms\Components\TextInput::make('classe')
                            ->label('Classe')
                            ->required()
                            ->maxLength(2)
                            ->placeholder('1, 2, 3, 4, 5, 6, 7, 8, 9')
                            ->helperText('Première classe du plan comptable'),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                'depense' => 'Dépense',
                                'recette' => 'Recette',
                            ])
                            ->required()
                            ->helperText('Type budgétaire'),

                        Forms\Components\Select::make('niveau')
                            ->label('Niveau hiérarchique')
                            ->options([
                                'classe' => 'Classe',
                                'compte' => 'Compte',
                                'sous_compte' => 'Sous-compte',
                                'ligne' => 'Ligne',
                            ])
                            ->required(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun parent (niveau classe)')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\NomenclatureBudgetaire::where('libelle', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [$item->id => "{$item->code} - {$item->libelle}"]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\NomenclatureBudgetaire::find($value)?->code . ' - ' .
                                    \App\Models\NomenclatureBudgetaire::find($value)?->libelle
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Exercice et Mise en vigueur')
                    ->schema([
                        Forms\Components\DatePicker::make('date_mise_en_vigueur')
                            ->label('Date de mise en vigueur')
                            ->default(now()->startOfYear()),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice budgétaire')
                            ->numeric()
                            ->default(now()->year)
                            ->minValue(2020)
                            ->maxValue(2050)
                            ->helperText('Année budgétaire (ex: 2026)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Historisation')
                    ->schema([
                        Forms\Components\TextInput::make('code_precedent')
                            ->label('Code précédent')
                            ->maxLength(20)
                            ->placeholder('Si le code a changé'),

                        Forms\Components\Textarea::make('motif_modification')
                            ->label('Motif de modification')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Métadonnées')
                    ->schema([
                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('classe')
                    ->label('Classe')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        '6' => 'danger',
                        '7' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'depense',
                        'success' => 'recette',
                    ]),

                Tables\Columns\TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Parent')
                    ->placeholder('-')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->parent ? $record->parent->code . ' - ' . \Str::limit($record->parent->libelle, 20) : '-'
                    ),

                Tables\Columns\TextColumn::make('exercice')
                    ->label('Exercice')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('date_mise_en_vigueur')
                    ->label('Mise en vigueur')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classe')
                    ->label('Classe')
                    ->options([
                        '1' => 'Classe 1',
                        '2' => 'Classe 2',
                        '3' => 'Classe 3',
                        '4' => 'Classe 4',
                        '5' => 'Classe 5',
                        '6' => 'Classe 6 - Dépenses',
                        '7' => 'Classe 7 - Recettes',
                        '8' => 'Classe 8',
                        '9' => 'Classe 9',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'depense' => 'Dépense',
                        'recette' => 'Recette',
                    ]),

                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                    ]),

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
            RelationManagers\TachesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNomenclatureBudgetaires::route('/'),
            'hierarchie' => Pages\HierarchieNomenclatureBudgetaire::route('/hierarchie'),
            'create' => Pages\CreateNomenclatureBudgetaire::route('/create'),
            'edit' => Pages\EditNomenclatureBudgetaire::route('/{record}/edit'),
            'import' => Pages\ImportNomenclatureBudgetaire::route('/import'),
        ];
    }
}
