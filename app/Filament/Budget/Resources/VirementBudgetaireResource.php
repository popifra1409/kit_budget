<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\VirementBudgetaireResource\Pages;
use App\Models\VirementBudgetaire;
use App\Models\Budget;
use App\Models\LigneBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class VirementBudgetaireResource extends Resource
{
    protected static ?string $model = VirementBudgetaire::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Virements Budgétaires';
    protected static ?string $modelLabel = 'Virement';
    protected static ?string $pluralModelLabel = 'Virements Budgétaires';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 2;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_virement_budgetaire') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_virement_budgetaire') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        if ($user->can('super_admin_virement_budgetaire')) return true;
        if (!$user->can('chef_service_budget_virement_budgetaire')) return false;
        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        if (!$user->can('super_admin_virement_budgetaire')) return false;
        return $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && $record->estLectureSeule()) {
            \Filament\Notifications\Notification::make()
                ->title('Édition impossible')->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }
        return $canEdit;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_virement_budgetaire') ?? false;
    }

    public static function canExecuter($record): bool
    {
        return auth()->user()?->can('executer_virement_budgetaire') ?? false;
    }

    public static function canAnnuler($record): bool
    {
        return auth()->user()?->can('annuler_virement_budgetaire') ?? false;
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

                Forms\Components\Section::make('Budget et Lignes')
                    ->schema([
                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()->searchable()->reactive()
                            ->afterStateUpdated(fn(callable $set) => $set('ligne_source_id', null) + $set('ligne_destination_id', null))
                            ->helperText('Sélectionnez d\'abord le budget'),

                        Forms\Components\Select::make('ligne_source_id')
                            ->label('Ligne source (qui perd du budget)')
                            ->required()->searchable()->reactive()
                            ->options(function (callable $get) {
                                $budgetId = $get('budget_id');
                                if (!$budgetId) return [];
                                return LigneBudgetaire::where('budget_id', $budgetId)
                                    ->with('nomenclature')->get()
                                    ->filter(fn($ligne) => $ligne->nomenclature !== null)
                                    ->mapWithKeys(fn($ligne) => [
                                        $ligne->id => $ligne->nomenclature->code . ' - ' . $ligne->nomenclature->libelle .
                                            ' (Disponible: ' . number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA)'
                                    ]);
                            })
                            ->helperText(function (callable $get) {
                                $ligneId = $get('ligne_source_id');
                                if (!$ligneId) return 'Ligne qui va céder du budget';
                                $ligne = LigneBudgetaire::find($ligneId);
                                return 'Disponible: ' . number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA';
                            }),

                        Forms\Components\Select::make('ligne_destination_id')
                            ->label('Ligne destination (qui reçoit du budget)')
                            ->required()->searchable()->reactive()
                            ->options(function (callable $get) {
                                $budgetId      = $get('budget_id');
                                $ligneSourceId = $get('ligne_source_id');
                                if (!$budgetId) return [];
                                return LigneBudgetaire::where('budget_id', $budgetId)
                                    ->where('id', '!=', $ligneSourceId)
                                    ->with('nomenclature')->get()
                                    ->filter(fn($ligne) => $ligne->nomenclature !== null)
                                    ->mapWithKeys(fn($ligne) => [
                                        $ligne->id => $ligne->nomenclature->code . ' - ' . $ligne->nomenclature->libelle .
                                            ' (Disponible: ' . number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA)'
                                    ]);
                            })
                            ->helperText(function (callable $get) {
                                $ligneId = $get('ligne_destination_id');
                                if (!$ligneId) return 'Ligne qui va recevoir du budget';
                                $ligne = LigneBudgetaire::find($ligneId);
                                return 'Disponible actuel : ' . number_format($ligne?->disponible_engagement ?? 0, 0, ',', ' ') . ' FCFA';
                            }),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Détails du Virement')
                    ->schema([
                        Forms\Components\TextInput::make('montant')
                            ->label('Montant à virer')->required()->numeric()
                            ->prefix('FCFA')->minValue(1)->helperText('Montant en FCFA')->reactive()
                            ->rules([
                                function (callable $get) {
                                    return function (string $attribute, $value, callable $fail) use ($get) {
                                        $ligneSourceId = $get('ligne_source_id');
                                        if ($ligneSourceId) {
                                            $ligne = LigneBudgetaire::find($ligneSourceId);
                                            if ($value > $ligne->disponible_engagement) {
                                                $fail("Le montant dépasse le disponible (" . number_format($ligne->disponible_engagement, 0, ',', ' ') . " FCFA)");
                                            }
                                        }
                                    };
                                },
                            ]),

                        Forms\Components\DatePicker::make('date_virement')
                            ->label('Date du virement')->required()->default(now()),

                        Forms\Components\Textarea::make('motif')
                            ->label('Motif du virement')->required()->rows(3)
                            ->placeholder('Ex: Renforcement du budget carburant suite à augmentation des prix')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('reference_decision')
                            ->label('Référence de la décision')->maxLength(255)
                            ->placeholder('Ex: Décision N°123/2026 du 15/01/2026')
                            ->helperText('Référence de la décision autorisant le virement'),
                    ])
                    ->columns(2),
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

                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')->searchable()->sortable()->badge()->color('info'),

                Tables\Columns\TextColumn::make('ligneSource.nomenclature.code')
                    ->label('Source')->searchable()
                    ->formatStateUsing(
                        fn($record) => ($record->ligneSource?->nomenclature?->code ?? 'N/A') . ' - ' .
                            \Str::limit($record->ligneSource?->nomenclature?->libelle ?? '', 25)
                    )
                    ->wrap(),

                Tables\Columns\IconColumn::make('direction')
                    ->label('')->icon('heroicon-o-arrow-right')->color('primary')->size('lg'),

                Tables\Columns\TextColumn::make('ligneDestination.nomenclature.code')
                    ->label('Destination')->searchable()
                    ->formatStateUsing(
                        fn($record) => ($record->ligneDestination?->nomenclature?->code ?? 'N/A') . ' - ' .
                            \Str::limit($record->ligneDestination?->nomenclature?->libelle ?? '', 25)
                    )
                    ->wrap(),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')->money('XAF')->sortable()->weight('bold')->color('warning'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'en_attente',
                        'success'   => 'approuve',
                        'primary'   => 'execute',
                        'danger'    => 'rejete',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'en_attente' => 'En attente',
                        'approuve'   => 'Approuvé',
                        'execute'    => 'Exécuté',
                        'rejete'     => 'Rejeté',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('date_virement')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('validateur.name')
                    ->label('Validé par')->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->relationship('budget', 'libelle')
                    ->searchable()->preload(),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'en_attente' => 'En attente',
                        'approuve'   => 'Approuvé',
                        'execute'    => 'Exécuté',
                        'rejete'     => 'Rejeté',
                    ]),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup, aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => $record->statut === 'en_attente'),

                    // ── Workflow ──────────────────────────────────
                    Tables\Actions\Action::make('approuver')
                        ->label('Approuver')
                        ->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn($record) => $record->statut === 'en_attente')
                        ->requiresConfirmation()
                        ->modalHeading('Approuver le virement')
                        ->modalDescription(
                            fn($record) =>
                            "Approuver le virement de " . number_format($record->montant, 0, ',', ' ') . " FCFA ?"
                        )
                        ->action(function ($record) {
                            $record->approuver(auth()->user());
                            Notification::make()->title('Virement approuvé')->success()->send();
                        }),

                    Tables\Actions\Action::make('executer')
                        ->label('Exécuter')
                        ->icon('heroicon-o-bolt')->color('primary')
                        ->visible(fn($record) => $record->statut === 'approuve')
                        ->requiresConfirmation()
                        ->modalHeading('Exécuter le virement')
                        ->modalDescription(
                            fn($record) =>
                            "Exécuter le virement de " . number_format($record->montant, 0, ',', ' ') . " FCFA ? Cette action est irréversible."
                        )
                        ->action(function ($record) {
                            try {
                                $record->executer();
                                Notification::make()
                                    ->title('Virement exécuté avec succès')->success()
                                    ->body('Les lignes budgétaires ont été mises à jour.')->send();
                            } catch (\Exception $e) {
                                Notification::make()->title('Erreur')->danger()->body($e->getMessage())->send();
                            }
                        }),

                    Tables\Actions\Action::make('rejeter')
                        ->label('Rejeter')
                        ->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn($record) => $record->statut === 'en_attente')
                        ->requiresConfirmation()
                        ->modalHeading('Rejeter le virement')
                        ->modalDescription('Êtes-vous sûr de vouloir rejeter ce virement ?')
                        ->action(function ($record) {
                            $record->rejeter(auth()->user());
                            Notification::make()->title('Virement rejeté')->warning()->send();
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVirementBudgetaires::route('/'),
            'create' => Pages\CreateVirementBudgetaire::route('/create'),
            'edit'   => Pages\EditVirementBudgetaire::route('/{record}/edit'),
            'view'   => Pages\ViewVirementBudgetaire::route('/{record}'),
        ];
    }
}
