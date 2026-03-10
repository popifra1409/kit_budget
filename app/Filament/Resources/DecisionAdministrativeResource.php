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
use App\Services\DecisionAdministrativePdfService;
use Illuminate\Database\Eloquent\Builder;

class DecisionAdministrativeResource extends Resource
{
    protected static ?string $model = DecisionAdministrative::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Décisions';

    protected static ?string $modelLabel = 'Décision';

    protected static ?string $pluralModelLabel = 'Décisions';

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

    public static function canRecuperer($record): bool
    {
        return auth()->user()?->can('recuperer_decision_administrative') ?? false;
    }

    public static function canEngager($record): bool
    {
        return auth()->check() && auth()->user()?->can('engager_decision_administrative') ?? false;
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
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\Select::make('service_emetteur_id')
                            ->label('Service émetteur')
                            ->options(\App\Models\Service::where('actif', true)->pluck('nom', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Service qui émet la décision')
                            ->columnSpan(1),

                        Forms\Components\ToggleButtons::make('type_beneficiaire')
                            ->label('Type de bénéficiaire')
                            ->options([
                                'personnel' => 'Personnel',
                                'fournisseur' => 'Fournisseur',
                            ])
                            ->icons([
                                'personnel' => 'heroicon-o-user',
                                'fournisseur' => 'heroicon-o-building-office',
                            ])
                            ->default('personnel')
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Réinitialiser l'autre champ quand on change de type
                                if ($state === 'personnel') {
                                    $set('fournisseur_id', null);
                                } else {
                                    $set('personnel_id', null);
                                }
                            })
                            ->columnSpanFull(),

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
                                ->required(fn(callable $get) => $get('type_beneficiaire') === 'personnel')
                                ->visible(fn(callable $get) => $get('type_beneficiaire') === 'personnel')
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
                                    return 'Sélectionnez un membre du personnel';
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
                                })
                                ->columnSpanFull(),

                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur concerné')
                            ->relationship('fournisseur', 'raison_sociale')
                            ->searchable()
                            ->preload()
                            ->required(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur')
                            ->visible(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur')
                            ->live()
                            ->helperText(function ($get) {
                                $fournisseurId = $get('fournisseur_id');
                                if ($fournisseurId) {
                                    $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);
                                    if ($fournisseur) {
                                        $info = "📋 {$fournisseur->raison_sociale}";
                                        if ($fournisseur->numero_contribuable) {
                                            $info .= " | N° Contribuable: {$fournisseur->numero_contribuable}";
                                        }
                                        if ($fournisseur->regimeFiscal) {
                                            $info .= " | Régime: {$fournisseur->regimeFiscal->libelle}";
                                        }
                                        return $info;
                                    }
                                }
                                return 'Sélectionnez un fournisseur';
                            })
                            ->createOptionForm([
                                Forms\Components\Section::make('Identification')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('raison_sociale')
                                                    ->label('Raison sociale')
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('sigle')
                                                    ->label('Sigle')
                                                    ->maxLength(50),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('numero_contribuable')
                                                    ->label('N° Contribuable')
                                                    ->maxLength(100),

                                                Forms\Components\Select::make('regime_fiscal_id')
                                                    ->label('Régime fiscal')
                                                    ->relationship('regimeFiscal', 'libelle')
                                                    ->searchable()
                                                    ->preload(),
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

                                        Forms\Components\Textarea::make('adresse')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->maxLength(500),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $fournisseur = \App\Models\Fournisseur::create($data);

                                \Filament\Notifications\Notification::make()
                                    ->title('Fournisseur créé')
                                    ->success()
                                    ->body("Le fournisseur {$fournisseur->raison_sociale} a été ajouté.")
                                    ->send();

                                return $fournisseur->id;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Type et objet')
                    ->schema([
                        Forms\Components\Select::make('type_decision_id')
                            ->label('Type de décision')
                            ->relationship('typeDecision', 'libelle', function ($query) {
                                return $query->actif()->ordonne();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->unique()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('libelle')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->rows(2),
                                Forms\Components\TextInput::make('ordre')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->createOptionModalHeading('Créer un type de décision'),

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

                Forms\Components\Section::make('Montants et retenues')
                    ->description('Le montant brut est le montant TTC (toutes taxes comprises). Le montant HT sera calculé automatiquement.')
                    ->schema([
                        // ========================================
                        // MONTANT BRUT (TTC) et TAUX TVA
                        // ========================================
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('montant_brut')
                                    ->label('Montant Brut (TTC)')
                                    ->numeric()
                                    ->required()
                                    ->prefix('FCFA')
                                    ->live(onBlur: true)
                                    ->helperText('Montant TTC incluant la TVA')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('type_tva')
                                    ->label('Type TVA')
                                    ->options([
                                        'taux' => 'Taux (%)',
                                        'forfait' => 'Forfait (FCFA)',
                                    ])
                                    ->default('taux')
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('Taux TVA (%)')
                                    ->numeric()
                                    ->default(19.25)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->helperText('TVA incluse dans le montant brut')
                                    ->columnSpan(1),

                                // ✅ NOUVEAU : Montant HT calculé automatiquement
                                Forms\Components\Placeholder::make('montant_ht_affiche')
                                    ->label('💰 Montant HT (calculé)')
                                    ->content(function (callable $get) {
                                        $brut = (float) ($get('montant_brut') ?? 0);
                                        $tauxTva = (float) ($get('taux_tva') ?? 19.25);

                                        if ($brut <= 0) {
                                            return '0 FCFA';
                                        }

                                        // Formule : HT = Brut / (1 + TVA/100)
                                        $montantHT = $brut / (1 + ($tauxTva / 100));

                                        return number_format($montantHT, 0, ',', ' ') . ' FCFA';
                                    })
                                    ->columnSpan(1),
                            ])
                            ->columnSpanFull(),

                        // ========================================
                        // CNPS
                        // ========================================
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('taux_cnps')
                                    ->label('CNPS (%)')
                                    ->numeric()
                                    ->placeholder(4.2)
                                    // ->default(0.0)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->live(onBlur: true),

                                Forms\Components\Placeholder::make('montant_cnps_calcule')
                                    ->label('Montant CNPS calculé')
                                    ->content(function (callable $get) {
                                        $brut = (float) ($get('montant_brut') ?? 0);
                                        $tauxTva = (float) ($get('taux_tva') ?? 19.25);
                                        $tauxCnps = (float) ($get('taux_cnps') ?? 0);

                                        if ($brut <= 0) {
                                            return '0 FCFA';
                                        }

                                        // Calculer HT
                                        $montantHT = $brut / (1 + ($tauxTva / 100));

                                        // Calculer CNPS sur HT
                                        $montantCnps = $montantHT * ($tauxCnps / 100);

                                        return number_format($montantCnps, 0, ',', ' ') . ' FCFA';
                                    }),
                            ])
                            ->columnSpanFull(),

                        // ========================================
                        // IRNC
                        // ========================================
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('taux_irnc')
                                    ->label('IRNC (%)')
                                    ->numeric()
                                    ->placeholder(11)
                                    // ->default(11)
                                    ->helperText('IR Non Commercial')
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->live(onBlur: true),

                                Forms\Components\Placeholder::make('montant_irnc_calcule')
                                    ->label('Montant IRNC calculé')
                                    ->content(function (callable $get) {
                                        $brut = (float) ($get('montant_brut') ?? 0);
                                        $tauxTva = (float) ($get('taux_tva') ?? 19.25);
                                        $tauxIrnc = (float) ($get('taux_irnc') ?? 0);

                                        if ($brut <= 0) {
                                            return '0 FCFA';
                                        }

                                        // Calculer HT
                                        $montantHT = $brut / (1 + ($tauxTva / 100));

                                        // Calculer IRNC sur HT
                                        $montantIrnc = $montantHT * ($tauxIrnc / 100);

                                        return number_format($montantIrnc, 0, ',', ' ') . ' FCFA';
                                    }),
                            ])
                            ->columnSpanFull(),

                        // ========================================
                        // REDEVANCE AUDIOVISUELLE
                        // ========================================
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type_redevance_audiovisuelle')
                                    ->label('Type Redevance audiovisuelle')
                                    ->options([
                                        'taux' => 'Taux (%)',
                                        'forfait' => 'Forfait (FCFA)',
                                    ])
                                    ->default('forfait')
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('taux_redevance_audiovisuelle')
                                    ->label('Taux Redevance (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->visible(fn(callable $get) => $get('type_redevance_audiovisuelle') === 'taux')
                                    ->live(onBlur: true),

                                Forms\Components\TextInput::make('montant_redevance_audiovisuelle')
                                    ->label('Montant Redevance')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('FCFA')
                                    ->visible(fn(callable $get) => $get('type_redevance_audiovisuelle') === 'forfait')
                                    ->live(onBlur: true),

                                Forms\Components\Placeholder::make('montant_redevance_calcule')
                                    ->label('Montant Redevance calculé')
                                    ->content(function (callable $get) {
                                        $typeRedevance = $get('type_redevance_audiovisuelle');

                                        if ($typeRedevance === 'taux') {
                                            $brut = (float) ($get('montant_brut') ?? 0);
                                            $tauxTva = (float) ($get('taux_tva') ?? 19.25);
                                            $tauxRedevance = (float) ($get('taux_redevance_audiovisuelle') ?? 0);

                                            if ($brut <= 0) {
                                                return '0 FCFA';
                                            }

                                            // Calculer HT
                                            $montantHT = $brut / (1 + ($tauxTva / 100));

                                            // Calculer redevance sur HT
                                            $montant = $montantHT * ($tauxRedevance / 100);
                                        } else {
                                            $montant = (float) ($get('montant_redevance_audiovisuelle') ?? 0);
                                        }

                                        return number_format($montant, 0, ',', ' ') . ' FCFA';
                                    })
                                    ->visible(fn(callable $get) => $get('type_redevance_audiovisuelle') === 'taux'),
                            ])
                            ->columnSpanFull(),

                        // ========================================
                        // FEICOM (Fonds Spécial d'Équipement et d'Intervention Intercommunale)
                        // ========================================
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type_feicom')
                                    ->label('Type FEICOM')
                                    ->options([
                                        'taux' => 'Taux (%)',
                                        'forfait' => 'Forfait (FCFA)',
                                    ])
                                    ->default('forfait')
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('taux_feicom')
                                    ->label('Taux FEICOM (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->visible(fn(callable $get) => $get('type_feicom') === 'taux')
                                    ->live(onBlur: true),

                                Forms\Components\TextInput::make('montant_feicom')
                                    ->label('Montant FEICOM')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('FCFA')
                                    ->visible(fn(callable $get) => $get('type_feicom') === 'forfait')
                                    ->live(onBlur: true),

                                Forms\Components\Placeholder::make('montant_feicom_calcule')
                                    ->label('Montant FEICOM calculé')
                                    ->content(function (callable $get) {
                                        $typeFeicom = $get('type_feicom');

                                        if ($typeFeicom === 'taux') {
                                            $brut = (float) ($get('montant_brut') ?? 0);
                                            $tauxTva = (float) ($get('taux_tva') ?? 19.25);
                                            $tauxFeicom = (float) ($get('taux_feicom') ?? 0);

                                            if ($brut <= 0) {
                                                return '0 FCFA';
                                            }

                                            // Calculer HT
                                            $montantHT = $brut / (1 + ($tauxTva / 100));

                                            // Calculer FEICOM sur HT
                                            $montant = $montantHT * ($tauxFeicom / 100);
                                        } else {
                                            $montant = (float) ($get('montant_feicom') ?? 0);
                                        }

                                        return number_format($montant, 0, ',', ' ') . ' FCFA';
                                    })
                                    ->visible(fn(callable $get) => $get('type_feicom') === 'taux'),
                            ])
                            ->columnSpanFull(),

                        // ========================================
                        // AUTRES RETENUES
                        // ========================================
                        Forms\Components\TextInput::make('autres_retenues')
                            ->label('Autres retenues')
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->columnSpanFull(),

                        // ========================================
                        // RÉSUMÉ DES TAXES
                        // ========================================
                        Forms\Components\Placeholder::make('resume_montants')
                            ->label('📊 Résumé des montants et calculs')
                            ->content(function (callable $get) {
                                $brut = (float) ($get('montant_brut') ?? 0);
                                $tauxTva = (float) ($get('taux_tva') ?? 19.25);

                                if ($brut <= 0) {
                                    return 'Veuillez saisir un montant brut';
                                }

                                // Calculer HT
                                $montantHT = $brut / (1 + ($tauxTva / 100));

                                // Calculer TVA
                                $montantTva = $montantHT * ($tauxTva / 100);

                                // CNPS (sur HT)
                                $tauxCnps = (float) ($get('taux_cnps') ?? 0);
                                $montantCnps = $montantHT * ($tauxCnps / 100);

                                // IRNC (sur HT)
                                $tauxIrnc = (float) ($get('taux_irnc') ?? 0);
                                $montantIrnc = $montantHT * ($tauxIrnc / 100);

                                // Redevance
                                $typeRedevance = $get('type_redevance_audiovisuelle') ?? 'forfait';
                                if ($typeRedevance === 'taux') {
                                    $tauxRedevance = (float) ($get('taux_redevance_audiovisuelle') ?? 0);
                                    $montantRedevance = $montantHT * ($tauxRedevance / 100);
                                    $labelRedevance = "Redevance audiovisuelle ({$tauxRedevance}%)";
                                } else {
                                    $montantRedevance = (float) ($get('montant_redevance_audiovisuelle') ?? 0);
                                    $labelRedevance = "Redevance audiovisuelle (forfait)";
                                }

                                // FEICOM
                                $typeFeicom = $get('type_feicom') ?? 'forfait';
                                if ($typeFeicom === 'taux') {
                                    $tauxFeicom = (float) ($get('taux_feicom') ?? 0);
                                    $montantFeicom = $montantHT * ($tauxFeicom / 100);
                                    $labelFeicom = "FEICOM ({$tauxFeicom}%)";
                                } else {
                                    $montantFeicom = (float) ($get('montant_feicom') ?? 0);
                                    $labelFeicom = "FEICOM (forfait)";
                                }

                                // Autres retenues
                                $autres = (float) ($get('autres_retenues') ?? 0);

                                // Total retenues et net
                                $totalRetenues = $montantCnps + $montantIrnc + $montantRedevance + $montantFeicom + $autres;
                                $net = $montantHT - $totalRetenues;

                                return collect([
                                    "💰 MONTANT BRUT (TTC) : " . number_format($brut, 0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "",
                                    "📐 DÉCOMPOSITION :",
                                    "   Montant HT : " . number_format($montantHT, 0, ',', ' ') . " FCFA",
                                    "   TVA ({$tauxTva}%) : " . number_format($montantTva, 0, ',', ' ') . " FCFA",
                                    "",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "",
                                    "💸 RETENUES (calculées sur HT = " . number_format($montantHT, 0, ',', ' ') . " FCFA) :",
                                    "   • CNPS ({$tauxCnps}%) : " . number_format($montantCnps, 0, ',', ' ') . " FCFA",
                                    "   • IRNC ({$tauxIrnc}%) : " . number_format($montantIrnc, 0, ',', ' ') . " FCFA",
                                    "   • {$labelRedevance} : " . number_format($montantRedevance, 0, ',', ' ') . " FCFA",
                                    "   • {$labelFeicom} : " . number_format($montantFeicom, 0, ',', ' ') . " FCFA",
                                    "   • Autres retenues : " . number_format($autres, 0, ',', ' ') . " FCFA",
                                    "",
                                    "   TOTAL RETENUES : " . number_format($totalRetenues, 0, ',', ' ') . " FCFA",
                                    "",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "",
                                    "✅ NET À PAYER : " . number_format($net, 0, ',', ' ') . " FCFA",
                                    "",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                ])->implode("\n");
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
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                // Tables\Columns\BadgeColumn::make('exercice.annee')
                //     ->label('Exercice')
                //     ->sortable()
                //     ->colors([
                //         'success' => fn($record) =>
                //         $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                //         'warning' => fn($record) =>
                //         $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                //         'danger' => fn($record) =>
                //         $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                //         'gray' => fn($record) =>
                //         $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                //     ])
                //     ->tooltip(
                //         fn($record) =>
                //         $record->exercice instanceof \App\Models\Exercice
                //             ? $record->exercice->libelle
                //             : null
                //     )
                //     ->toggleable(),

                Tables\Columns\TextColumn::make('numero')
                    ->label('N° DA')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                // Tables\Columns\TextColumn::make('budget.code')
                //     ->label('Budget')
                //     ->searchable()
                //     ->badge()
                //     ->color('info'),

                Tables\Columns\TextColumn::make('typeDecision.libelle')
                    ->label('Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('type_beneficiaire')
                    ->label('Bénéficiaire')
                    ->formatStateUsing(function ($record) {
                        $nom = $record->getNomCompletPersonnel();
                        $type = match ($record->type_beneficiaire) {
                            'personnel' => '👤',
                            'fournisseur' => '🏢',
                            default => '',
                        };
                        return "{$type} {$nom}";
                    })
                    ->description(fn($record) => match ($record->type_beneficiaire) {
                        'personnel' => $record->personnel?->matricule ?? '',
                        'fournisseur' => $record->fournisseur?->numero_contribuable ?? '',
                        default => '',
                    })
                    ->searchable(['personnel.nom', 'personnel.prenom', 'fournisseur.raison_sociale'])
                    ->sortable()
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

                Tables\Filters\Filter::make('date_decision')
                    ->form([
                        Forms\Components\DatePicker::make('date_decision_from')
                            ->label('Date d\'émission du')
                            ->placeholder('JJ/MM/AAAA'),
                        Forms\Components\DatePicker::make('date_decision_until')
                            ->label('Date d\'émission au')
                            ->placeholder('JJ/MM/AAAA'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_decision_from'], fn($q, $date) =>
                            $q->whereDate('date_decision', '>=', $date))
                            ->when($data['date_decision_until'], fn($q, $date) =>
                            $q->whereDate('date_decision', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_emission_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Émis depuis le ' . \Carbon\Carbon::parse($data['date_decision_from'])->format('d/m/Y'))
                                ->removeField('date_decision_from');
                        }

                        if ($data['date_emission_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Émis jusqu\'au ' . \Carbon\Carbon::parse($data['date_decision_until'])->format('d/m/Y'))
                                ->removeField('date_decision_until');
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
                            ->default('today')
                            ->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? 'today';

                        return match ($periode) {
                            'today' => $query->whereDate('date_decision', today()),
                            'yesterday' => $query->whereDate('date_decision', today()->subDay()),
                            'this_week' => $query->whereBetween('date_decision', [
                                now()->startOfWeek(),
                                now()->endOfWeek()
                            ]),
                            'last_week' => $query->whereBetween('date_decision', [
                                now()->subWeek()->startOfWeek(),
                                now()->subWeek()->endOfWeek()
                            ]),
                            'this_month' => $query->whereMonth('date_decision', now()->month)
                                ->whereYear('date_decision', now()->year),
                            'last_month' => $query->whereMonth('date_decision', now()->subMonth()->month)
                                ->whereYear('date_decision', now()->subMonth()->year),
                            'this_quarter' => $query->whereBetween('date_decision', [
                                now()->startOfQuarter(),
                                now()->endOfQuarter()
                            ]),
                            'last_quarter' => $query->whereBetween('date_decision', [
                                now()->subQuarter()->startOfQuarter(),
                                now()->subQuarter()->endOfQuarter()
                            ]),
                            'this_year' => $query->whereYear('date_decision', now()->year),
                            'last_year' => $query->whereYear('date_decision', now()->subYear()->year),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) {
                            return 'Période : Aujourd\'hui';
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

                Tables\Filters\Filter::make('mes_decisions')
                    ->label('📌 Mes décisions actifs')
                    ->query(function ($query) {
                        $userId = auth()->id();

                        return $query->where(function ($q) use ($userId) {
                            // 1. Mes brouillons (pas encore transmis)
                            $q->where(function ($subQ) use ($userId) {
                                $subQ->where('created_by', $userId)
                                    ->where('statut', 'brouillon')
                                    ->whereDoesntHave('transmissions', function ($t) {
                                        $t->where('statut', 'en_attente');
                                    });
                            })
                                // OU
                                // 2. Transmis À MOI (en attente de mon action)
                                ->orWhereHas('transmissions', function ($transmission) use ($userId) {
                                    $transmission->where('destinataire_id', $userId)
                                        ->where('statut', 'en_attente');
                                })
                                // OU
                                // 3. Retournés À MOI pour correction
                                ->orWhere(function ($subQ) use ($userId) {
                                    $subQ->where('created_by', $userId)
                                        ->whereHas('transmissions', function ($transmission) {
                                            $transmission->where('statut', 'retourne')
                                                ->latest()
                                                ->limit(1);
                                        });
                                });
                        });
                    })
                    ->toggle()
                    ->default(true) // ✅ ACTIVÉ par défaut
                    ->indicateUsing(fn() => '📌 Décisions nécessitant mon action'),

                Tables\Filters\Filter::make('mes_transmissions')
                    ->label('📤 Mes transmissions envoyées')
                    ->query(function ($query) {
                        return $query->whereHas('transmissions', function ($transmission) {
                            $transmission->where('expediteur_id', auth()->id())
                                ->where('statut', 'en_attente');
                        });
                    })
                    ->toggle()
                    ->default(false) // ✅ DÉSACTIVÉ par défaut (cache les transmissions)
                    ->indicateUsing(fn() => '📤 Transmissions envoyées en attente'),

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
                    ->default(false), // Activé par défaut

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
            ->actions(
                WorkflowActions::make(
                    avecEngagement: true,
                    // pdfServiceClass: DecisionAdministrativePdfService::class,
                    // pdfRouteName: 'decisions-administratives.pdf.preview'
                )
            )
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

    /**
     * ✅ Garantir des valeurs par défaut AVANT la création
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Taux → 0 par défaut
        $data['taux_cnps'] = $data['taux_cnps'] ?? 0;
        $data['taux_irnc'] = $data['taux_irnc'] ?? 0;
        $data['taux_tva'] = $data['taux_tva'] ?? 0;
        $data['taux_redevance_audiovisuelle'] = $data['taux_redevance_audiovisuelle'] ?? 0;
        $data['taux_feicom'] = $data['taux_feicom'] ?? 0;

        // Montants → 0 par défaut
        $data['montant_cnps'] = $data['montant_cnps'] ?? 0;
        $data['montant_irnc'] = $data['montant_irnc'] ?? 0;
        $data['montant_tva'] = $data['montant_tva'] ?? 0;
        $data['montant_redevance_audiovisuelle'] = $data['montant_redevance_audiovisuelle'] ?? 0;
        $data['montant_feicom'] = $data['montant_feicom'] ?? 0;
        $data['autres_retenues'] = $data['autres_retenues'] ?? 0;

        // Textes → chaîne vide par défaut
        $data['reference_decision'] = $data['reference_decision'] ?? '';
        $data['signataire'] = $data['signataire'] ?? '';
        $data['observations'] = $data['observations'] ?? '';

        return $data;
    }

    /**
     * ✅ Garantir des valeurs par défaut AVANT la mise à jour
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateFormDataBeforeCreate($data);
    }
}
