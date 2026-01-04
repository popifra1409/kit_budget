<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DecisionAdministrativeResource\Pages;
use App\Models\DecisionAdministrative;
use App\Models\Budget;
use App\Models\User;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class DecisionAdministrativeResource extends Resource
{
    protected static ?string $model = DecisionAdministrative::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Décisions Administratives';

    protected static ?string $modelLabel = 'Décision Administrative';

    protected static ?string $pluralModelLabel = 'Décisions Administratives';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 3;

    /**
     * Permissions - Décisions administratives
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
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        // 1. Vérifier que l'utilisateur est connecté
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // 2. Super admin peut toujours éditer (même exercices clos)
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 3. Vérifier le rôle requis
        if (!$user->hasAnyRole(['operateur_budget', 'chef_service_budget', 'sous_directeur_budget', 'directeur_general'])) {
            return false;
        }

        // 4. Vérifier que l'exercice est modifiable
        return $record->estModifiable();
    }


    public static function canDelete($record): bool
    {
        // 1. Vérifier que l'utilisateur est connecté
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // 2. Seul super admin peut supprimer
        if (!$user->hasRole('super_admin')) {
            return false;
        }

        // 3. Même super admin ne peut pas supprimer sur exercice archivé
        return $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);

        if (!$canEdit && $record->estLectureSeule()) {
            \Filament\Notifications\Notification::make()
                ->title('Édition impossible')
                ->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }

        return $canEdit;
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
     * Action spéciale : Valider une décision (Directeur Général)
     */
    public static function canValider($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'controleur_financier',
            'directeur_general',
        ]) : false;
    }

    /**
     * Action spéciale : Annuler une décision
     */
    public static function canAnnuler($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'directeur_general'
        ]) : false;
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

                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Select::make('personnel_id')
                            ->label('Personnel')
                            ->options(User::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Sélectionner si le personnel est un utilisateur'),

                        Forms\Components\TextInput::make('nom_personnel')
                            ->label('Nom du personnel (si non utilisateur)')
                            ->maxLength(255)
                            ->placeholder('Ex: Jean DUPONT')
                            ->visible(fn(callable $get) => !$get('personnel_id')),

                        Forms\Components\TextInput::make('matricule')
                            ->label('Matricule')
                            ->maxLength(255)
                            ->placeholder('Ex: MAT-2024-001'),

                        Forms\Components\TextInput::make('fonction')
                            ->label('Fonction')
                            ->maxLength(255)
                            ->placeholder('Ex: Chef de Service'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Type et objet')
                    ->schema([
                        Forms\Components\Select::make('type_decision')
                            ->label('Type de décision')
                            ->options([
                                'avancement' => 'Avancement',
                                'promotion' => 'Promotion',
                                'prime' => 'Prime',
                                'indemnite' => 'Indemnité',
                                'formation' => 'Formation',
                                'mission' => 'Mission',
                                'affectation' => 'Affectation',
                                'autre' => 'Autre',
                            ])
                            ->required()
                            ->searchable(),

                        Forms\Components\DatePicker::make('date_decision')
                            ->label('Date de décision')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('date_effet')
                            ->label('Date de prise d\'effet')
                            ->after('date_decision'),

                        Forms\Components\DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->after('date_effet')
                            ->helperText('Pour missions, formations, etc.'),

                        Forms\Components\Textarea::make('objet')
                            ->label('Objet de la décision')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Prime exceptionnelle de fin d\'année')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Montants')
                    ->schema([
                        Forms\Components\TextInput::make('montant_brut')
                            ->label('Montant Brut')
                            ->required()
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->live(onBlur: true)
                            ->helperText('Les calculs CNPS et IR seront automatiques'),

                        Forms\Components\TextInput::make('autres_retenues')
                            ->label('Autres retenues')
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->live(onBlur: true),

                        Forms\Components\Placeholder::make('calculs_auto')
                            ->label('Calculs automatiques')
                            ->content(function (callable $get) {
                                $brut = (float) ($get('montant_brut') ?? 0);
                                $autresRetenues = (float) ($get('autres_retenues') ?? 0);

                                // CNPS 4.2%
                                $cnps = $brut * 0.042;

                                // IR selon barème (simplifié)
                                if ($brut <= 62000) {
                                    $ir = 0;
                                } elseif ($brut <= 130000) {
                                    $ir = ($brut - 62000) * 0.10;
                                } elseif ($brut <= 200000) {
                                    $ir = 6800 + ($brut - 130000) * 0.15;
                                } elseif ($brut <= 333000) {
                                    $ir = 17300 + ($brut - 200000) * 0.25;
                                } else {
                                    $ir = 50550 + ($brut - 333000) * 0.35;
                                }

                                $net = $brut - $cnps - $ir - $autresRetenues;

                                return "CNPS (4.2%): " . number_format($cnps, 0, ',', ' ') . " FCFA\n" .
                                    "IR: " . number_format($ir, 0, ',', ' ') . " FCFA\n" .
                                    "Net à payer: " . number_format($net, 0, ',', ' ') . " FCFA";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Références')
                    ->schema([
                        Forms\Components\TextInput::make('reference_decision')
                            ->label('Référence de la décision')
                            ->maxLength(255)
                            ->placeholder('Ex: N° Arrêté, Note de service')
                            ->helperText('N° de l\'arrêté, note de service, etc.'),

                        Forms\Components\TextInput::make('signataire')
                            ->label('Signataire')
                            ->maxLength(255)
                            ->placeholder('Ex: Directeur Général'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(2)
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

                Tables\Columns\TextColumn::make('numero')
                    ->label('N° DA')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('type_decision')
                    ->label('Type')
                    ->colors([
                        'success' => 'prime',
                        'info' => 'mission',
                        'warning' => 'formation',
                        'primary' => fn($state) => in_array($state, ['avancement', 'promotion']),
                        'secondary' => fn($state) => !in_array($state, ['prime', 'mission', 'formation', 'avancement', 'promotion']),
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'avancement' => 'Avancement',
                        'promotion' => 'Promotion',
                        'prime' => 'Prime',
                        'indemnite' => 'Indemnité',
                        'formation' => 'Formation',
                        'mission' => 'Mission',
                        'affectation' => 'Affectation',
                        'autre' => 'Autre',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('personnel')
                    ->label('Personnel')
                    ->formatStateUsing(fn($record) => $record->getNomCompletPersonnel())
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('date_decision')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Montant Brut')
                    ->money('XAF')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning' => 'validee',
                        'primary' => 'engagee',
                        'info' => 'ordonnancee',
                        'success' => fn($state) => in_array($state, ['liquidee', 'payee']),
                        'danger' => 'annulee',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon' => 'Brouillon',
                        'validee' => 'Validée',
                        'engagee' => 'Engagée',
                        'ordonnancee' => 'Ordonnancée',
                        'liquidee' => 'Liquidée',
                        'payee' => 'Payée',
                        'annulee' => 'Annulée',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('engagee')
                    ->label('Engagée')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->relationship('budget', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type_decision')
                    ->label('Type')
                    ->options([
                        'avancement' => 'Avancement',
                        'promotion' => 'Promotion',
                        'prime' => 'Prime',
                        'indemnite' => 'Indemnité',
                        'formation' => 'Formation',
                        'mission' => 'Mission',
                        'affectation' => 'Affectation',
                        'autre' => 'Autre',
                    ]),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'validee' => 'Validée',
                        'engagee' => 'Engagée',
                        'ordonnancee' => 'Ordonnancée',
                        'liquidee' => 'Liquidée',
                        'payee' => 'Payée',
                        'annulee' => 'Annulée',
                    ]),

                Tables\Filters\TernaryFilter::make('engagee')
                    ->label('Engagée')
                    ->placeholder('Toutes')
                    ->trueLabel('Engagées')
                    ->falseLabel('Non engagées'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->estModifiable()),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->valider(auth()->user());
                        Notification::make()
                            ->title('Décision validée')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('engager')
                    ->label('Engager')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn($record) => $record->statut === 'validee' && !$record->engagee)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire')
                            ->options(function (callable $get, $record) {
                                return \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->with('nomenclature')
                                    ->get()
                                    ->mapWithKeys(fn($lb) => [
                                        $lb->nomenclature_id => "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} (Dispo: " .
                                            number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Sélectionner la ligne budgétaire sur laquelle imputer cette dépense'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $record->engagerBudget($data['nomenclature_id']);
                            Notification::make()
                                ->title('Budget engagé avec succès')
                                ->success()
                                ->body("Montant net engagé: " . number_format($record->montant_net, 0, ',', ' ') . " FCFA")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur lors de l\'engagement')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListDecisionAdministratives::route('/'),
            'create' => Pages\CreateDecisionAdministrative::route('/create'),
            'edit' => Pages\EditDecisionAdministrative::route('/{record}/edit'),
            'view' => Pages\ViewDecisionAdministrative::route('/{record}'),
        ];
    }
}
