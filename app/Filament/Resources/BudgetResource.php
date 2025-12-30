<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetResource\Pages;
use App\Filament\Resources\BudgetResource\RelationManagers;
use App\Models\Budget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Budgets';

    protected static ?string $modelLabel = 'Budget';

    protected static ?string $pluralModelLabel = 'Budgets';

    protected static ?string $navigationGroup = 'Gestion Budgétaire';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions - Budget avec adoption et activation
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
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier',
            'agence_comptable'
        ]) : false;
    }

    /**
     * Action spéciale : Adopter un budget (Directeur Général)
     */
    public static function canAdopter($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'directeur_general'
        ]) : false;
    }

    /**
     * Action spéciale : Activer un budget (Super Admin)
     */
    public static function canActiver($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du Budget')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: BUD-2026')
                            ->helperText('Code unique du budget'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Budget Primitif 2026'),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice budgétaire')
                            ->required()
                            ->numeric()
                            ->default(now()->year)
                            ->minValue(2020)
                            ->maxValue(2050),

                        Forms\Components\DatePicker::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->helperText('Date de vote/adoption du budget'),

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

                Tables\Columns\TextColumn::make('exercice')
                    ->label('Exercice')
                    ->sortable()
                    ->badge()
                    ->color('info'),

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
                    ->counts('lignesBudgetaires')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('budget_total')
                    ->label('Budget Total')
                    ->formatStateUsing(fn($record) => number_format($record->getBudgetTotalRectifie(), 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('taux_execution')
                    ->label('Taux exec.')
                    ->formatStateUsing(fn($record) => number_format($record->getTauxExecution(), 1) . '%')
                    ->color(fn($record) => $record->getTauxExecution() >= 80 ? 'success' : ($record->getTauxExecution() >= 50 ? 'warning' : 'danger')),

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
                Tables\Filters\SelectFilter::make('exercice')
                    ->label('Exercice')
                    ->options(function () {
                        $currentYear = now()->year;
                        return collect(range($currentYear - 2, $currentYear + 3))
                            ->mapWithKeys(fn($year) => [$year => $year]);
                    }),

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
            RelationManagers\LignesBudgetairesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit' => Pages\EditBudget::route('/{record}/edit'),
            'view' => Pages\ViewBudget::route('/{record}'),
        ];
    }
}
