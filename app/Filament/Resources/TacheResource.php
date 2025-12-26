<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TacheResource\Pages;
use App\Models\Tache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TacheResource extends Resource
{
    protected static ?string $model = Tache::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Tâches';

    protected static ?string $modelLabel = 'Tâche';

    protected static ?string $pluralModelLabel = 'Tâches';

    protected static ?string $navigationGroup = 'Cadre Logique';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Liaison')
                    ->schema([
                        Forms\Components\Select::make('activite_id')
                            ->label('Activité')
                            ->relationship('activite', 'libelle')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                "{$record->action->programme->code} > {$record->action->code} > {$record->code} - {$record->libelle}"
                            ),

                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature Budgétaire (Dépenses uniquement)')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\NomenclatureBudgetaire::where('type', 'depense')
                                    ->where('actif', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [$item->id => "{$item->code} - {$item->libelle}"]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\NomenclatureBudgetaire::find($value)?->code . ' - ' .
                                    \App\Models\NomenclatureBudgetaire::find($value)?->libelle
                            )
                            ->helperText('Seulement les lignes budgétaires de type dépense'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informations de la Tâche')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Ex: T1'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Achat de médicaments'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Gestion et Responsabilité')
                    ->schema([
                        Forms\Components\TextInput::make('delai')
                            ->label('Délai de réalisation')
                            ->maxLength(255)
                            ->placeholder('Ex: 3 mois, T1 2026'),

                        Forms\Components\TextInput::make('guichet')
                            ->label('Guichet')
                            ->maxLength(255)
                            ->placeholder('Ex: Guichet Pharmacie'),

                        Forms\Components\Select::make('service_id')
                            ->label('Service responsable')
                            ->relationship('service', 'nom')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique('services', 'code')
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('nom')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('responsable')
                                    ->label('Responsable')
                                    ->maxLength(255),
                            ])
                            ->helperText('Service responsable de la tâche'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Budget')
                    ->schema([
                        Forms\Components\TextInput::make('ae')
                            ->label('AE (Autorisation d\'Engagement)')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->placeholder('0')
                            ->helperText('Montant de l\'autorisation d\'engagement'),

                        Forms\Components\TextInput::make('cp')
                            ->label('CP (Crédit de Paiement)')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->placeholder('0')
                            ->helperText('Montant du crédit de paiement'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Résultats et Indicateurs')
                    ->schema([
                        Forms\Components\Textarea::make('resultat_attendu')
                            ->label('Résultat attendu')
                            ->rows(2)
                            ->placeholder('Ex: Disponibilité permanente des médicaments essentiels'),

                        Forms\Components\Textarea::make('indicateur_resultat')
                            ->label('Indicateur de résultat')
                            ->rows(2)
                            ->placeholder('Ex: Tous les patients satisfaits, Réduction du nombre de malades'),
                    ])
                    ->columns(2),

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
                Tables\Columns\TextColumn::make('activite.action.programme.code')
                    ->label('Prog.')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('activite.action.code')
                    ->label('Action')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('activite.code')
                    ->label('Activité')
                    ->searchable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('service.nom')
                    ->label('Service')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ae')
                    ->label('AE')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cp')
                    ->label('CP')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('activite_id')
                    ->label('Activité')
                    ->relationship('activite', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('nomenclature_id')
                    ->label('Nomenclature')
                    ->relationship('nomenclature', 'libelle')
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
            'index' => Pages\ListTaches::route('/'),
            'create' => Pages\CreateTache::route('/create'),
            'edit' => Pages\EditTache::route('/{record}/edit'),
        ];
    }
}
