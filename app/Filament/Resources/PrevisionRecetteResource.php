<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrevisionRecetteResource\Pages;
use App\Filament\Resources\PrevisionRecetteResource\RelationManagers;
use App\Models\PrevisionRecette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class PrevisionRecetteResource extends Resource
{
    protected static ?string $model = PrevisionRecette::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Prévisions de Recettes';

    protected static ?string $modelLabel = 'Prévision de Recettes';

    protected static ?string $pluralModelLabel = 'Prévisions de Recettes';

    protected static ?string $navigationGroup = 'Gestion Budgétaire';

    protected static ?int $navigationSort = 2;

    /**
     * Permissions - Prévisions de recettes
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier',
            'agence_comptable'
        ]) : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]) : false;
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

        if (!$user->hasAnyRole(['chef_service_budget'])) {
            return false;
        }

        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (!$user->hasRole('super_admin')) {
            return false;
        }

        return $record->estModifiable();
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
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

                Forms\Components\Section::make('Informations de la Prévision')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: PREV-REC-2026')
                            ->helperText('Code unique de la prévision de recettes'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Prévisions de Recettes 2026'),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice budgétaire')
                            ->required()
                            ->numeric()
                            ->default(now()->year)
                            ->minValue(2020)
                            ->maxValue(2050),

                        Forms\Components\DatePicker::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->helperText('Date de vote/adoption de la prévision'),

                        Forms\Components\DatePicker::make('date_revision')
                            ->label('Date de révision')
                            ->helperText('Date de dernière révision'),

                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'elaboration' => 'En élaboration',
                                'adopte' => 'Adopté',
                                'execution' => 'En exécution',
                                'cloture' => 'Clôturé',
                            ])
                            ->required()
                            ->default('elaboration'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                        'danger' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                        'gray' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(
                        fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice
                            ? $record->exercice->libelle
                            : null
                    )
                    ->toggleable(),

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

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'elaboration',
                        'success' => 'adopte',
                        'warning' => 'execution',
                        'danger' => 'cloture',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'elaboration' => 'Élaboration',
                        'adopte' => 'Adopté',
                        'execution' => 'Exécution',
                        'cloture' => 'Clôturé',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')
                    ->counts('lignesPrevisions')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_prevu')
                    ->label('Total Prévu')
                    ->formatStateUsing(fn($record) => number_format($record->getTotalPrevuRectifie(), 0, ',', ' ') . ' FCFA')
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_recouvre')
                    ->label('Total Recouvré')
                    ->formatStateUsing(fn($record) => number_format($record->getTotalRecouvre(), 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('taux_recouvrement')
                    ->label('Taux')
                    ->formatStateUsing(fn($record) => number_format($record->getTauxRecouvrement(), 1) . '%')
                    ->badge()
                    ->color(fn($record) => $record->getTauxRecouvrement() >= 90 ? 'success' : ($record->getTauxRecouvrement() >= 70 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('date_adoption')
                    ->label('Date adoption')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'elaboration' => 'Élaboration',
                        'adopte' => 'Adopté',
                        'execution' => 'Exécution',
                        'cloture' => 'Clôturé',
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
            ->defaultSort('exercice', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LignesPrevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrevisionRecettes::route('/'),
            'create' => Pages\CreatePrevisionRecette::route('/create'),
            'edit' => Pages\EditPrevisionRecette::route('/{record}/edit'),
            'view' => Pages\ViewPrevisionRecette::route('/{record}'),
        ];
    }
}
