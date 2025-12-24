<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NomenclatureBudgetaireResource\Pages;
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
                            ->placeholder('Ex: 601300'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Carburants et lubrifiants'),

                        Forms\Components\Select::make('classe')
                            ->label('Classe')
                            ->options([
                                '6' => 'Classe 6 - Dépenses (Charges)',
                                '7' => 'Classe 7 - Recettes (Produits)',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                'depense' => 'Dépense',
                                'recette' => 'Recette',
                            ])
                            ->required(),

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
                            ->relationship('parent', 'libelle')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun parent (niveau classe)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Période de validité')
                    ->schema([
                        Forms\Components\DatePicker::make('date_debut_validite')
                            ->label('Date de début de validité')
                            ->required()
                            ->default(now()->startOfYear()),

                        Forms\Components\DatePicker::make('date_fin_validite')
                            ->label('Date de fin de validité')
                            ->placeholder('En cours (laisser vide)'),
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
                    ->collapsible(),

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
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('classe')
                    ->label('Classe')
                    ->colors([
                        'danger' => '6',
                        'success' => '7',
                    ]),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'depense',
                        'success' => 'recette',
                    ]),

                Tables\Columns\TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge(),

                Tables\Columns\TextColumn::make('date_debut_validite')
                    ->label('Début validité')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_fin_validite')
                    ->label('Fin validité')
                    ->date('d/m/Y')
                    ->placeholder('En cours')
                    ->sortable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classe')
                    ->label('Classe')
                    ->options([
                        '6' => 'Classe 6 - Dépenses',
                        '7' => 'Classe 7 - Recettes',
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNomenclatureBudgetaires::route('/'),
            'create' => Pages\CreateNomenclatureBudgetaire::route('/create'),
            'edit' => Pages\EditNomenclatureBudgetaire::route('/{record}/edit'),
            'import' => Pages\ImportNomenclatureBudgetaire::route('/import'),
        ];
    }
}
