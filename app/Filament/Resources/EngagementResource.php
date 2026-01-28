<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EngagementResource\Pages;
use App\Models\Engagement;
use App\Models\Budget;
use App\Models\Fournisseur;
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


class EngagementResource extends Resource
{
    protected static ?string $model = Engagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Engagements';

    protected static ?string $modelLabel = 'Engagement';

    protected static ?string $pluralModelLabel = 'Engagements';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 1;


    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Transmission::query()
            ->where('document_type', 'App\Models\Engagement')
            ->pourDestinataire(auth()->id())
            ->enAttente()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }


    /**
     * Permissions – Engagements budgétaires
     */
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_engagement');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_engagement');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_engagement');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (!$user->can('update_engagement')) {
            return false;
        }

        if (!$record->estModifiable()) {
            if ($record->estLectureSeule()) {
                \Filament\Notifications\Notification::make()
                    ->title('Modification impossible')
                    ->warning()
                    ->body(
                        "L'exercice {$record->exercice->annee} est {$record->exercice->statut}.
                    Modification interdite."
                    )
                    ->send();
            }

            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('delete_engagement')) {
            return false;
        }

        return $record->estModifiable();
    }

    /**
     * Action spéciale : Valider un engagement
     * (Contrôle financier)
     */
    public static function canValider($record): bool
    {
        return auth()->check()
            && auth()->user()->can('valider_engagement');
    }

    /**
     * Action spéciale : Annuler un engagement
     */
    public static function canAnnuler($record): bool
    {
        return auth()->check()
            && auth()->user()->can('annuler_engagement');
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
                            ->options(Budget::where('actif', true)
                                ->whereNotNull('libelle')
                                ->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn(callable $set) => $set('nomenclature_principale_id', null)),

                        Forms\Components\Select::make('type_engagement')
                            ->label('Type d\'engagement')
                            ->options([
                                'BC' => 'Bon de Commande',
                                'DA' => 'Décision Administrative',
                                'Mission' => 'Ordre de mission',
                                'Avance' => 'Avance sur solde',
                                'Formation' => 'Formation',
                                'Lettre-commande' => 'Lettre-commande',
                                'Marché' => 'Marché',
                                'Subvention' => 'Subvention',
                                'Prime' => 'Prime exceptionnelle',
                                'Autre' => 'Autre',
                            ])
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText('Type d\'engagement (extensible à tout type)'),

                        Forms\Components\DatePicker::make('date_engagement')
                            ->label('Date d\'engagement')
                            ->required()
                            ->default(now()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Nomenclature et montant')
                    ->schema([
                        Forms\Components\Select::make('nomenclature_principale_id')
                            ->label('Nomenclature budgétaire principale')
                            ->options(function (callable $get) {
                                $budgetId = $get('budget_id');
                                if (!$budgetId) {
                                    return [];
                                }

                                $lignes = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                    ->with('nomenclature')
                                    ->get()
                                    ->filter(fn($lb) => $lb->nomenclature) // Filtrer les NULL
                                    ->mapWithKeys(fn($lb) => [
                                        $lb->nomenclature_id =>
                                        "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} " .
                                            "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                    ]);

                                return $lignes->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText(
                                fn(callable $get) =>
                                !$get('budget_id')
                                    ? 'Veuillez d\'abord sélectionner un budget'
                                    : 'Ligne budgétaire sur laquelle imputer cet engagement'
                            )
                            ->disabled(fn(callable $get) => !$get('budget_id')),

                        Forms\Components\TextInput::make('montant_engage')
                            ->label('Montant à engager')
                            ->required()
                            ->numeric()
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->helperText(function (callable $get) {
                                $nomenclatureId = $get('nomenclature_principale_id');
                                $budgetId = $get('budget_id');
                                $montant = $get('montant_engage');

                                if (!$nomenclatureId || !$budgetId || !$montant) {
                                    return '';
                                }

                                $ligne = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                    ->where('nomenclature_id', $nomenclatureId)
                                    ->first();

                                if (!$ligne) {
                                    return '';
                                }

                                if ($montant > $ligne->disponible_engagement) {
                                    return '⚠️ Crédit insuffisant ! Disponible: ' .
                                        number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA';
                                }

                                return '✅ Crédit disponible: ' .
                                    number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA';
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Forms\Components\Radio::make('type_beneficiaire')
                            ->label('Type de bénéficiaire')
                            ->options([
                                'fournisseur' => 'Fournisseur',
                                'personnel' => 'Personnel (Agent)',
                            ])
                            ->required()
                            ->live()
                            ->default('fournisseur')
                            ->inline(),

                        Forms\Components\Select::make('beneficiaire_fournisseur_id')
                            ->label('Fournisseur')
                            ->options(Fournisseur::whereNotNull('raison_sociale')
                                ->pluck('raison_sociale', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur')
                            ->visible(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur'),

                        Forms\Components\Select::make('beneficiaire_personnel_id')
                            ->label('Personnel')
                            ->options(User::whereNotNull('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn(callable $get) => $get('type_beneficiaire') === 'personnel')
                            ->visible(fn(callable $get) => $get('type_beneficiaire') === 'personnel'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objet et référence')
                    ->schema([
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet de l\'engagement')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Fourniture de matériel informatique, Mission Yaoundé...')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('reference_document')
                            ->label('Référence du document')
                            ->maxLength(255)
                            ->placeholder('Ex: BC-2025-001, Arrêté n°...')
                            ->helperText('Référence du document justificatif (optionnel)'),
                    ]),

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
                    ->label('N° Engagement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('type_engagement')
                    ->label('Type')
                    ->colors([
                        'primary' => 'BC',
                        'success' => fn($state) => in_array($state, ['DA', 'Prime']),
                        'warning' => fn($state) => in_array($state, ['Mission', 'Formation']),
                        'info' => 'Avance',
                        'secondary' => fn($state) => !in_array($state, ['BC', 'DA', 'Prime', 'Mission', 'Formation', 'Avance']),
                    ]),

                Tables\Columns\TextColumn::make('nomenclaturePrincipale.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('beneficiaire')
                    ->label('Bénéficiaire')
                    ->formatStateUsing(fn($record) => $record->getNomBeneficiaire())
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('date_engagement')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'provisoire',
                        'success' => 'definitif',
                        'danger' => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'provisoire' => 'Provisoire',
                        'definitif' => 'Définitif',
                        'annule' => 'Annulé',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('engageable_type')
                    ->label('Source')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'App\Models\BonCommande' => 'BC',
                        'App\Models\DecisionAdministrative' => 'DA',
                        null => 'Manuel',
                        default => 'Autre',
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_engagement')
                    ->form([
                        Forms\Components\DatePicker::make('date_engagement_from')
                            ->label('Date d\'engagement du')
                            ->placeholder('JJ/MM/AAAA'),
                        Forms\Components\DatePicker::make('date_engagement_until')
                            ->label('Date d\'engagement au')
                            ->placeholder('JJ/MM/AAAA'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_engagement_from'], fn($q, $date) =>
                            $q->whereDate('date_engagement', '>=', $date))
                            ->when($data['date_engagement_until'], fn($q, $date) =>
                            $q->whereDate('date_engagement', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_engagement_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Engagé depuis le ' . \Carbon\Carbon::parse($data['date_engagement_from'])->format('d/m/Y'))
                                ->removeField('date_engagement_from');
                        }

                        if ($data['date_engagement_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Engagé jusqu\'au ' . \Carbon\Carbon::parse($data['date_engagement_until'])->format('d/m/Y'))
                                ->removeField('date_engagement_until');
                        }

                        return $indicators;
                    }),

                // FILTRE PAR PÉRIODE PRÉDÉFINIE
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période prédéfinie')
                            ->options([
                                'today' => 'Aujourd\'hui',
                                'yesterday' => 'Hier',
                                'this_week' => 'Cette semaine',
                                'last_week' => 'Semaine dernière',
                                'this_month' => 'Ce mois',
                                'last_month' => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year' => 'Cette année',
                                'last_year' => 'Année dernière',
                            ])
                            ->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? null;

                        if (!$periode) {
                            return $query;
                        }

                        return match ($periode) {
                            'today' => $query->whereDate('date_engagement', today()),
                            'yesterday' => $query->whereDate('date_engagement', today()->subDay()),
                            'this_week' => $query->whereBetween('date_engagement', [
                                now()->startOfWeek(),
                                now()->endOfWeek()
                            ]),
                            'last_week' => $query->whereBetween('date_engagement', [
                                now()->subWeek()->startOfWeek(),
                                now()->subWeek()->endOfWeek()
                            ]),
                            'this_month' => $query->whereMonth('date_engagement', now()->month)
                                ->whereYear('date_engagement', now()->year),
                            'last_month' => $query->whereMonth('date_engagement', now()->subMonth()->month)
                                ->whereYear('date_engagement', now()->subMonth()->year),
                            'this_quarter' => $query->whereBetween('date_engagement', [
                                now()->startOfQuarter(),
                                now()->endOfQuarter()
                            ]),
                            'last_quarter' => $query->whereBetween('date_engagement', [
                                now()->subQuarter()->startOfQuarter(),
                                now()->subQuarter()->endOfQuarter()
                            ]),
                            'this_year' => $query->whereYear('date_engagement', now()->year),
                            'last_year' => $query->whereYear('date_engagement', now()->subYear()->year),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) {
                            return null;
                        }

                        $labels = [
                            'today' => 'Aujourd\'hui',
                            'yesterday' => 'Hier',
                            'this_week' => 'Cette semaine',
                            'last_week' => 'Semaine dernière',
                            'this_month' => 'Ce mois',
                            'last_month' => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year' => 'Cette année',
                            'last_year' => 'Année dernière',
                        ];

                        return 'Période : ' . ($labels[$data['periode']] ?? $data['periode']);
                    }),

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

                Tables\Filters\SelectFilter::make('type_engagement')
                    ->label('Type')
                    ->options([
                        'BC' => 'Bon de Commande',
                        'DA' => 'Décision Administrative',
                        'Mission' => 'Ordre de mission',
                        'Avance' => 'Avance',
                        'Formation' => 'Formation',
                        'Lettre-commande' => 'Lettre-commande',
                        'Marché' => 'Marché',
                    ]),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'provisoire' => 'Provisoire',
                        'definitif' => 'Définitif',
                        'annule' => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'provisoire' && !$record->engageable_id),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->statut === 'provisoire')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->statut = 'definitif';
                        $record->save();

                        Notification::make()
                            ->title('Engagement validé')
                            ->success()
                            ->send();
                    }),
                // Groupe d'actions pour télécharger/aperçu
                Tables\Actions\ActionGroup::make([
                    // Certificat d'engagement
                    Tables\Actions\Action::make('telecharger_certificat')
                        ->label('Certificat (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'certificat_engagement',
                            'id' => $record->id
                        ])),

                    Tables\Actions\Action::make('afficher_certificat')
                        ->label('Certificat (Aperçu)')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'certificat_engagement',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),

                    // Autorisation d'engagement
                    Tables\Actions\Action::make('telecharger_autorisation')
                        ->label('Autorisation (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'autorisation_engagement',
                            'id' => $record->id
                        ])),

                    Tables\Actions\Action::make('afficher_autorisation')
                        ->label('Autorisation (Aperçu)')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'autorisation_engagement',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),

                    // Fiche de performance
                    Tables\Actions\Action::make('telecharger_fiche')
                        ->label('Fiche Perf. (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'fiche_performance',
                            'id' => $record->id
                        ])),

                    Tables\Actions\Action::make('afficher_fiche')
                        ->label('Fiche Perf. (Aperçu)')
                        ->icon('heroicon-o-eye')
                        ->color('secondary')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'fiche_performance',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Télécharger / Aperçu')
                    ->icon('heroicon-m-document-arrow-down')
                    ->size('sm')
                    ->color('success')
                    ->button(),
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
            'index' => Pages\ListEngagements::route('/'),
            'create' => Pages\CreateEngagement::route('/create'),
            'edit' => Pages\EditEngagement::route('/{record}/edit'),
            'view' => Pages\ViewEngagement::route('/{record}'),
        ];
    }
}
