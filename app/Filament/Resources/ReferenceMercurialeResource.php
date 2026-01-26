<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferenceMercurialeResource\Pages;
use App\Models\ReferenceMercuriale;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;

class ReferenceMercurialeResource extends Resource
{
    protected static ?string $model = ReferenceMercuriale::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Références Mercuriales';

    protected static ?string $modelLabel = 'Référence Mercuriale';

    protected static ?string $pluralModelLabel = 'Références Mercuriales';

    protected static ?string $navigationGroup = 'Configuration Budget';

    protected static ?int $navigationSort = 5;

    /**
     * ==========================================
     * Permissions – Références Mercuriales
     * ==========================================
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_reference_mercuriale') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_reference_mercuriale') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_reference_mercuriale') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_reference_mercuriale')) {
            return false;
        }

        // Règle métier : modifiable uniquement si l'exercice est modifiable
        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_reference_mercuriale')) {
            return false;
        }

        // Règle métier : suppression uniquement si l'exercice est modifiable
        return $record->estModifiable();
    }

    /**
     * Action métier personnalisée : Activer/Désactiver
     */
    public static function canActiver($record): bool
    {
        return auth()->user()?->can('update_reference_mercuriale') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations de la référence')
                    ->schema([
                        Forms\Components\TextInput::make('code_reference')
                            ->label('Code de référence')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Ex: REF-2025-001')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('designation')
                            ->label('Désignation')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Ordinateur portable HP EliteBook')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('unite')
                            ->label('Unité')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pièce, kg, m, etc.')
                            ->default('pièce'),

                        Forms\Components\TextInput::make('prix_reference')
                            ->label('Prix de référence')
                            ->required()
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('rubrique')
                            ->label('Rubrique')
                            ->maxLength(255)
                            ->placeholder('Ex: Informatique'),

                        Forms\Components\TextInput::make('sous_rubrique')
                            ->label('Sous-rubrique')
                            ->maxLength(255)
                            ->placeholder('Ex: Matériel informatique'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice?->estActif(),
                        'warning' => fn($record) => $record->exercice?->estCloture(),
                        'danger' => fn($record) => $record->exercice?->estArchive(),
                        'gray' => fn($record) => $record->exercice?->estBrouillon(),
                    ]),

                Tables\Columns\TextColumn::make('code_reference')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('rubrique')
                    ->label('Rubrique')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sous_rubrique')
                    ->label('Sous-rubrique')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('prix_reference')
                    ->label('Prix référence')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('rubrique')
                    ->label('Rubrique')
                    ->options(fn() => ReferenceMercuriale::distinct()->pluck('rubrique', 'rubrique')->filter()),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code_reference', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferenceMercuriales::route('/'),
            'create' => Pages\CreateReferenceMercuriale::route('/create'),
            'edit' => Pages\EditReferenceMercuriale::route('/{record}/edit'),
        ];
    }
}
