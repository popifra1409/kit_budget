<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\BudgetResource\Pages;
use App\Filament\Budget\Resources\BudgetResource\RelationManagers;
use App\Models\Budget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use Filament\Tables\Actions\Action;
use App\Exports\DisponibilitesBudgetExport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Filament\Notifications\Notification;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Dépenses';
    protected static ?string $modelLabel = 'Dépense';
    protected static ?string $pluralModelLabel = 'Dépenses';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_budget') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_budget') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_budget') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user?->can('update_budget')) return false;

        if (!$record->estModifiable()) {
            Notification::make()
                ->title('Budget verrouillé')->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}.")
                ->send();
            return false;
        }
        return true;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_budget') && $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && $record->estLectureSeule()) {
            Notification::make()
                ->title('Édition impossible')->warning()
                ->body("Le budget {$record->exercice->annee} est en lecture seule.")
                ->send();
        }
        return $canEdit;
    }

    public static function canAdopter($record): bool
    {
        return auth()->user()?->can('adopter_budget') && $record->statut === 'propose';
    }

    public static function canActiver($record): bool
    {
        return auth()->user()?->can('activer_budget') && $record->statut === 'adopte';
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([ExerciceSelect::make()])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations du Budget')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')->required()->unique(ignoreRecord: true)
                            ->maxLength(50)->placeholder('Ex: BUD-2026')
                            ->helperText('Code unique du budget'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: Budget Primitif 2026'),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice budgétaire')->required()->numeric()
                            ->default(now()->year)->minValue(2020)->maxValue(2050),

                        Forms\Components\DatePicker::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->helperText('Date de vote/adoption du budget'),

                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'elaboration' => 'En élaboration',
                                'adopte'      => 'Adopté',
                                'execution'   => 'En exécution',
                                'cloture'     => 'Clôturé',
                            ])
                            ->required()->default('elaboration'),

                        Forms\Components\Toggle::make('actif')->label('Actif')->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')->rows(3)->columnSpanFull(),
                    ])
                    ->collapsible()->collapsed(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                        'danger'  => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                        'gray'    => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(fn($record) => $record->exercice instanceof \App\Models\Exercice ? $record->exercice->libelle : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->limit(50)->wrap(),

                Tables\Columns\TextColumn::make('exercice')
                    ->label('Exercice')->sortable()->badge()->color('info'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'elaboration',
                        'success'   => 'adopte',
                        'warning'   => 'execution',
                        'danger'    => 'cloture',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'elaboration' => 'Élaboration',
                        'adopte'      => 'Adopté',
                        'execution'   => 'Exécution',
                        'cloture'     => 'Clôturé',
                        default       => $state,
                    }),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')->counts('lignesBudgetaires')->badge()->color('primary'),

                Tables\Columns\TextColumn::make('budget_total')
                    ->label('Budget Total')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->lignesBudgetaires->sum('budget_rectifie'), 0, ',', ' ') . ' FCFA'
                    )
                    ->color('success'),

                Tables\Columns\TextColumn::make('taux_execution')
                    ->label('Taux exec.')
                    ->formatStateUsing(function ($record) {
                        $total = $record->lignesBudgetaires->sum('budget_rectifie');
                        $paye  = $record->lignesBudgetaires->sum('paye');
                        $taux  = $total > 0 ? ($paye / $total) * 100 : 0;
                        return number_format($taux, 1) . '%';
                    })
                    ->color(function ($record) {
                        $total = $record->lignesBudgetaires->sum('budget_rectifie');
                        $paye  = $record->lignesBudgetaires->sum('paye');
                        $taux  = $total > 0 ? ($paye / $total) * 100 : 0;
                        return $taux >= 80 ? 'success' : ($taux >= 50 ? 'warning' : 'danger');
                    }),

                Tables\Columns\TextColumn::make('date_adoption')
                    ->label('Date adoption')->date('d/m/Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

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
                        'adopte'      => 'Adopté',
                        'execution'   => 'Exécution',
                        'cloture'     => 'Clôturé',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')->placeholder('Tous')
                    ->trueLabel('Actifs')->falseLabel('Inactifs'),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup, aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // ── Export Excel ──────────────────────────────
                    Action::make('exportExcel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Exporter les disponibilités en Excel')
                        ->modalDescription(
                            fn(Budget $record) =>
                            "Exporter l'état des disponibilités budgétaires pour : {$record->libelle}"
                        )
                        ->modalSubmitActionLabel('Télécharger')
                        ->visible(fn() => auth()->user()->hasAnyRole([
                            'super_admin',
                            'chef_service_budget',
                            'sous_directeur_budget',
                            'directeur_general',
                            'controleur_financier',
                        ]))
                        ->action(function (Budget $record) {
                            $filename = 'disponibilites_'
                                . str_replace(' ', '_', $record->code) . '_'
                                . now()->format('Ymd_His') . '.xlsx';
                            return Excel::download(new DisponibilitesBudgetExport($record), $filename);
                        }),

                    // ── Export PDF ────────────────────────────────
                    Action::make('exportPdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Exporter les disponibilités en PDF')
                        ->modalDescription(
                            fn(Budget $record) =>
                            "Générer un document PDF avec l'état des disponibilités pour : {$record->libelle}"
                        )
                        ->modalSubmitActionLabel('Générer PDF')
                        ->visible(fn() => auth()->user()->hasAnyRole([
                            'super_admin',
                            'chef_service_budget',
                            'sous_directeur_budget',
                            'directeur_general',
                            'controleur_financier',
                        ]))
                        ->action(function (Budget $record) {
                            $lignes = $record->lignesBudgetaires()->with(['nomenclature'])->get();
                            $pdf = PDF::loadView('exports.disponibilites-budget-pdf', [
                                'budget' => $record,
                                'lignes' => $lignes,
                            ]);
                            $pdf->setPaper('a4', 'landscape');
                            $filename = 'disponibilites_'
                                . str_replace(' ', '_', $record->code) . '_'
                                . now()->format('Ymd_His') . '.pdf';
                            return response()->streamDownload(fn() => print($pdf->stream()), $filename);
                        }),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

            ], position: ActionsPosition::BeforeColumns)

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
            'index'  => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit'   => Pages\EditBudget::route('/{record}/edit'),
            'view'   => Pages\ViewBudget::route('/{record}'),
        ];
    }
}
