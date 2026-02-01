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
use App\Filament\Actions\WorkflowActions;
use Illuminate\Database\Eloquent\Builder;

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
     * Permissions – Décisions administratives
     */
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_decision_administrative');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_decision_administrative');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_decision_administrative');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (!$user->can('update_decision_administrative')) {
            return false;
        }

        // Si en cours de transmission, seul force_update peut modifier
        if ($record->estEnCoursDeTransmission()) {
            return $user->can('force_update_decision_administrative');
        }

        if (!$record->estModifiable()) {
            if ($record->exercice && !$record->exercice->estModifiable()) {
                \Filament\Notifications\Notification::make()
                    ->title('Modification impossible')
                    ->warning()
                    ->body(
                        "L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Modification interdite."
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

        if (!auth()->user()->can('delete_decision_administrative')) {
            return false;
        }

        return $record->estModifiable();
    }

    /**
     * Action spéciale : Valider une décision
     */
    public static function canValider($record): bool
    {
        return auth()->check()
            && auth()->user()->can('valider_decision_administrative');
    }

    /**
     * Action spéciale : Annuler une décision
     */
    public static function canAnnuler($record): bool
    {
        return auth()->check()
            && auth()->user()->can('annuler_decision_administrative');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with('exercice');

        $user = auth()->user();

        // Super admin voit TOUT
        if ($user && $user->hasRole('super_admin')) {
            return $query;
        }

        if (!$user) {
            return $query->whereRaw('1 = 0'); // Aucun résultat
        }

        // Pour les autres utilisateurs
        return $query->where(function ($q) use ($user) {
            // 1. Décisions créées par moi (toujours visibles)
            $q->where('created_by', $user->id)

                // OU

                // 2. Décisions sans transmission en cours (tout le monde peut voir)
                ->orWhereDoesntHave('transmissions', function ($transmission) {
                    $transmission->where('statut', 'en_attente');
                })

                // OU

                // 3. Décisions dont je suis le destinataire actuel
                ->orWhereHas('transmissions', function ($transmission) use ($user) {
                    $transmission->where('statut', 'en_attente')
                        ->where('destinataire_id', $user->id);
                });
        });
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
                            ->label('Personnel concerné')
                            ->options(function () {
                                return \App\Models\Personnel::query()
                                    ->where('actif', true)
                                    ->orderBy('nom')
                                    ->orderBy('prenoms')
                                    ->get()
                                    ->mapWithKeys(function ($personnel) {
                                        $label = "{$personnel->matricule} - {$personnel->nom} {$personnel->prenoms}";
                                        if ($personnel->fonction) {
                                            $label .= " ({$personnel->fonction})";
                                        }
                                        return [$personnel->id => $label];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(function ($get) {
                                $personnelId = $get('personnel_id');
                                if ($personnelId) {
                                    $personnel = \App\Models\Personnel::find($personnelId);
                                    if ($personnel) {
                                        $info = "📋 Matricule: {$personnel->matricule}";
                                        if ($personnel->fonction) {
                                            $info .= " | Fonction: {$personnel->fonction}";
                                        }
                                        if ($personnel->service) {
                                            $info .= " | Service: {$personnel->service->nom}";
                                        }
                                        return $info;
                                    }
                                }
                                return 'Sélectionnez un membre du personnel ou créez une nouvelle fiche';
                            })
                            ->createOptionForm([
                                Forms\Components\Section::make('Identité')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('matricule')
                                                    ->label('Matricule')
                                                    ->default(fn() => \App\Models\Personnel::genererMatricule())
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->required()
                                                    ->maxLength(50),

                                                Forms\Components\Select::make('civilite')
                                                    ->label('Civilité')
                                                    ->options([
                                                        'M.' => 'M.',
                                                        'Mme' => 'Mme',
                                                        'Mlle' => 'Mlle',
                                                    ]),

                                                Forms\Components\Select::make('sexe')
                                                    ->label('Sexe')
                                                    ->options([
                                                        'M' => 'Masculin',
                                                        'F' => 'Féminin',
                                                    ])
                                                    ->required(),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('nom')
                                                    ->label('Nom')
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('prenoms')
                                                    ->label('Prénoms')
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Affectation')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('service_id')
                                                    ->label('Service')
                                                    ->options(\App\Models\Service::where('actif', true)->pluck('nom', 'id'))
                                                    ->searchable()
                                                    ->preload(),

                                                Forms\Components\TextInput::make('fonction')
                                                    ->label('Fonction')
                                                    ->maxLength(255)
                                                    ->required(),
                                            ]),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('grade')
                                                    ->label('Grade')
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('categorie')
                                                    ->label('Catégorie')
                                                    ->maxLength(255)
                                                    ->placeholder('A, B, C, D'),

                                                Forms\Components\TextInput::make('echelon')
                                                    ->label('Échelon')
                                                    ->maxLength(255),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Contact')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('telephone')
                                                    ->label('Téléphone')
                                                    ->tel()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->maxLength(255),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $personnel = \App\Models\Personnel::create($data);

                                \Filament\Notifications\Notification::make()
                                    ->title('Personnel créé')
                                    ->success()
                                    ->body("Le personnel {$personnel->nom_complet} a été ajouté.")
                                    ->send();

                                return $personnel->id;
                            }),

                        Forms\Components\Select::make('service_emetteur_id')
                            ->label('Service émetteur')
                            ->options(\App\Models\Service::where('actif', true)->pluck('nom', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Service qui émet la décision'),
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

                Tables\Columns\TextColumn::make('transmission_status')
                    ->label('Transmission')
                    ->getStateUsing(function ($record) {
                        $transmission = $record->transmissions()
                            ->where('statut', 'en_attente')
                            ->latest()
                            ->first();

                        if (!$transmission) {
                            return null;
                        }

                        // Si je suis le destinataire
                        if ($transmission->destinataire_id === auth()->id()) {
                            return 'À traiter';
                        }

                        // Si je suis l'expéditeur
                        if ($transmission->expediteur_id === auth()->id()) {
                            return 'En attente chez ' . $transmission->destinataire->name;
                        }

                        // Sinon affichage générique
                        return 'Transmis à ' . $transmission->destinataire->name;
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state === 'À traiter' => 'warning',
                        str_starts_with($state ?? '', 'En attente') => 'info',
                        default => 'gray'
                    })
                    ->icon(fn($state) => match (true) {
                        $state === 'À traiter' => 'heroicon-o-bell-alert',
                        str_starts_with($state ?? '', 'En attente') => 'heroicon-o-clock',
                        default => 'heroicon-o-paper-airplane'
                    })
                    ->placeholder('-')
                    ->toggleable(),
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

                Tables\Filters\Filter::make('mes_transmissions')
                    ->label('Mes transmissions envoyées')
                    ->query(function ($query) {
                        return $query->whereHas('transmissions', function ($transmission) {
                            $transmission->where('expediteur_id', auth()->id())
                                ->where('statut', 'en_attente');
                        });
                    })
                    ->toggle(),

                Tables\Filters\Filter::make('a_traiter')
                    ->label('À traiter par moi')
                    ->query(function ($query) {
                        $userId = auth()->id();

                        return $query->where(function ($q) use ($userId) {
                            // 1. Décisions créées par moi et en brouillon
                            $q->where(function ($subQ) use ($userId) {
                                $subQ->where('created_by', $userId)
                                    ->where('statut', 'brouillon');
                            })
                                // OU
                                // 2. Décisions transmises à moi (en attente)
                                ->orWhereHas('transmissions', function ($transmission) use ($userId) {
                                    $transmission->where('destinataire_id', $userId)
                                        ->where('statut', 'en_attente');
                                });
                        });
                    })
                    ->toggle()
                    ->default(), // Activé par défaut
            ])
            ->actions(WorkflowActions::make(avecEngagement: true))
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
