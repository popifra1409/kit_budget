<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\BonCommandeResource\Pages;
use App\Filament\Budget\Resources\BonCommandeResource\RelationManagers;
use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Cache;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use App\Models\User;
use App\Filament\Actions\WorkflowActions;
use App\Services\BonCommandePdfService;
use App\Filament\Clusters\GestionBudgetaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\DB;

class BonCommandeResource extends Resource
{
    protected static ?string $model = BonCommande::class;

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Bons de Commande';

    protected static ?string $modelLabel = 'Bon de Commande';

    protected static ?string $pluralModelLabel = 'Bons de Commande';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'numero';

    protected static int $globalSearchResultsLimit = 20;

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Transmission::query()
            ->where('document_type', 'App\Models\BonCommande')
            ->pourDestinataire(auth()->id())
            ->enAttente()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }



    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'objet', 'statut', 'engage'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Objet' => $record->objet,
            'statut' => $record->statut,
            'Fournisseur' => $record->fournisseur->raison_sociale,
            'Ligne imputation' => $record->getNomenclaturePrincipale()->code ?: 'NON ENGANGE'
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['fournisseur']);
    }

    /**
     * ==========================================
     * Permissions – Bons de commande
     * ==========================================
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_bon_commande') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_bon_commande') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_bon_commande') ?? false;
    }

    /**
     * Édition : permission + exercice modifiable
     */
    public static function canEdit($record): bool
    {
        // Si en cours de transmission, PERSONNE ne peut modifier (sauf super admin avec force)
        if ($record->estEnCoursDeTransmission()) {
            return auth()->user()?->can('force_update_bon_commande') ?? false;
        }

        // Logique normale : force_update OU (update ET brouillon)
        return auth()->user()?->can('force_update_bon_commande')
            || (
                auth()->user()?->can('update_bon_commande')
                && $record->statut === 'brouillon'
            );
    }

    /**
     * Suppression : permission admin + exercice modifiable
     */
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('force_delete_bon_commande')
            || (
                auth()->user()?->can('delete_bon_commande')
                && $record->statut === 'brouillon'
            );
    }

    public static function canEngager($record): bool
    {
        return auth()->user()?->can('engager_bon_commande') ?? false;
    }

    public static function canDesengager($record): bool
    {
        return auth()->user()?->can('desengager_bon_commande') ?? false;
    }

    /**
     * Action spéciale : Valider
     */
    public static function canValider($record): bool
    {
        return auth()->user()?->can('valider_bon_commande') ?? false;
    }

    /**
     * Action spéciale : Annuler
     */
    public static function canAnnuler($record): bool
    {
        return auth()->user()?->can('annuler_bon_commande') ?? false;
    }

    public static function canRecuperer($record): bool
    {
        return auth()->user()?->can('recuperer_bon_commande') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        // ✅ avecReports=true si super_admin
                        ExerciceSelect::make(
                            avecReports: auth()->user()?->hasAnyRole(['super_admin', 'admin'])
                        ),
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
                            ->preload(),

                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur')
                            ->relationship('fournisseur', 'raison_sociale')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(debounce: 1000)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state) {
                                    return;
                                }

                                $fournisseur = Cache::remember(
                                    "fournisseur_{$state}_with_regime",
                                    now()->addMinutes(5),
                                    fn() => \App\Models\Fournisseur::with('regimeFiscal')->find($state)
                                );

                                // $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($state);

                                if (!$fournisseur || !$fournisseur->regimeFiscal) {
                                    return;
                                }

                                // Recalculer l'IR sur toutes les lignes avec le type d'engagement
                                $typeEngagementId = $get('type_engagement_id');
                                $lignes = $get('lignes') ?? [];

                                foreach ($lignes as $index => $ligne) {
                                    $qte = (float) ($ligne['quantite'] ?? 0);
                                    $pu = (float) ($ligne['prix_unitaire_ht'] ?? 0);
                                    $ht = $qte * $pu;

                                    if ($ht > 0 && $typeEngagementId) {
                                        $type = \App\Models\TypeEngagement::find($typeEngagementId);
                                        if ($type) {
                                            $tauxIR = $type->calculerTauxIR($fournisseur->regimeFiscal);
                                            $lignes[$index]['taux_ir'] = $tauxIR;
                                        }
                                    }
                                }

                                $set('lignes', $lignes);
                            })
                            ->helperText(function (callable $get) {
                                $fournisseurId = $get('fournisseur_id');
                                if ($fournisseurId) {
                                    $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);
                                    if ($fournisseur && $fournisseur->regimeFiscal) {
                                        return "Régime : {$fournisseur->regimeFiscal->libelle} - IR par défaut : {$fournisseur->regimeFiscal->taux_ir_defaut}%";
                                    }
                                }
                                return 'Sélectionnez un fournisseur';
                            })
                            ->createOptionForm([
                                Forms\Components\Section::make('Identification')
                                    ->schema([
                                        // Code fournisseur généré automatiquement
                                        Forms\Components\TextInput::make('code')
                                            ->label('Code Fournisseur')
                                            ->default(fn() => \App\Models\Fournisseur::genererCode())
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Généré automatiquement')
                                            ->columnSpanFull(),

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
                                                    ->preload()
                                                    ->required()
                                                    ->helperText('Obligatoire pour le calcul de l\'IR'),
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
                                    ->body("Le fournisseur {$fournisseur->raison_sociale} a été ajouté avec le code {$fournisseur->code}.")
                                    ->send();

                                return $fournisseur->id;
                            }),

                        Forms\Components\Select::make('service_demandeur_id')
                            ->label('Service demandeur')
                            ->options(Service::where('actif', true)->pluck('nom', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Dates et Délais')
                    ->description('Précisez soit une date précise, soit un délai de livraison')
                    ->schema([
                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('date_livraison_prevue')
                            ->label('Date de livraison prévue')
                            ->nullable()
                            ->helperText('Laisser vide si vous préférez indiquer un délai'),

                        Forms\Components\TextInput::make('delai_livraison')
                            ->label('OU Délai de livraison')
                            ->placeholder('Ex: 30 jours à date, 45 jours après signature')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objet')
                    ->schema([
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet du bon de commande')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Fourniture de matériel informatique')
                            ->autocomplete()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Taxes et charges')
                    ->description('Taxes applicables selon le type d\'engagement et le fournisseur')
                    ->schema([
                        Forms\Components\Select::make('type_engagement_id')
                            ->label('Type d\'engagement')
                            ->required()
                            ->relationship('typeEngagement', 'libelle')
                            ->searchable()
                            ->preload()
                            ->live(debounce: 1000)
                            ->helperText(function (callable $get) {
                                $typeId = $get('type_engagement_id');
                                if ($typeId) {
                                    $type = \App\Models\TypeEngagement::find($typeId);
                                    if ($type) {
                                        return $type->description;
                                    }
                                }

                                // Suggestion automatique
                                $montantTTC = $get('montant_ttc') ?? 0;
                                if ($montantTTC > 0) {
                                    $typeSuggere = \App\Models\TypeEngagement::determinerParMontant($montantTTC);
                                    if ($typeSuggere) {
                                        return "💡 Suggestion : {$typeSuggere->libelle}";
                                    }
                                }

                                return 'Le type sera déterminé automatiquement selon le montant';
                            }),

                        Forms\Components\Toggle::make('produit_importe')
                            ->label('Produit importé (soumis à TSR)')
                            ->helperText('Activez si les produits viennent de l\'étranger')
                            ->live(debounce: 1000)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('info_taxes')
                            ->label('Information')
                            ->content(function (callable $get) {
                                $typeId = $get('type_engagement_id');
                                $fournisseurId = $get('fournisseur_id');

                                if (!$typeId || !$fournisseurId) {
                                    return 'Sélectionnez un type d\'engagement et un fournisseur pour voir les taxes applicables';
                                }

                                $type = \App\Models\TypeEngagement::find($typeId);
                                $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);

                                if (!$type || !$fournisseur) {
                                    return '-';
                                }

                                $info = "📊 Taxes applicables :\n\n";

                                // IR
                                $tauxIR = $type->calculerTauxIR($fournisseur->regimeFiscal);
                                $info .= "• IR : {$tauxIR}% ";

                                if ($type->mode_calcul_ir === 'fixe') {
                                    $info .= "(taux fixe pour {$type->libelle})\n";
                                } elseif ($type->mode_calcul_ir === 'selon_regime') {
                                    $regime = $fournisseur->regimeFiscal?->libelle ?? 'Non défini';
                                    $info .= "(selon régime {$regime})\n";
                                }

                                // TSR
                                if ($get('produit_importe')) {
                                    $info .= "• TSR : Applicable (produit importé)\n";
                                }

                                // TVA
                                $info .= "• TVA : 19.25% (par défaut)\n";

                                return $info;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('montant_tsr')
                                    ->label('TSR')
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->disabled(fn(callable $get) => !$get('produit_importe'))
                                    ->helperText('Taxe Statistique Régionale'),

                                Forms\Components\TextInput::make('montant_cnps')
                                    ->label('CNPS')
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->helperText('Cotisations sociales'),

                                Forms\Components\TextInput::make('montant_irnc')
                                    ->label('IRNC')
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->helperText('IR Non Commercial'),

                                Forms\Components\TextInput::make('montant_autres_taxes')
                                    ->label('Autres taxes')
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->helperText('Autres prélèvements'),
                            ]),

                        Forms\Components\Placeholder::make('total_taxes')
                            ->label('Total des taxes et prélèvements')
                            ->content(function (callable $get) {
                                $total = ($get('montant_tva') ?? 0)
                                    + ($get('montant_ir') ?? 0)
                                    + ($get('montant_tsr') ?? 0)
                                    + ($get('montant_cnps') ?? 0)
                                    + ($get('montant_irnc') ?? 0)
                                    + ($get('montant_autres_taxes') ?? 0);

                                return number_format($total, 0, ',', ' ') . ' FCFA';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),


                Forms\Components\Section::make('Taux Communs et Exonérations')
                    ->description('Appliquez des taux communs à toutes les lignes ou gérez les exonérations')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                // ===== TVA COMMUNE =====
                                Forms\Components\TextInput::make('tva_commune')
                                    ->label('TVA Commune (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Ex: 19.25')
                                    ->live(debounce: 500)
                                    ->dehydrated(true) // ✅ CORRIGÉ : était false, ne sauvegardait rien
                                    ->disabled(fn(callable $get) => (bool) $get('exonere_tva')) // ✅ Désactivé si exonéré
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // ✅ Ne pas appliquer si exonération active
                                        if ($get('exonere_tva')) return;

                                        $taux  = (float) ($state ?? 0);
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            $set("lignes.$index.taux_tva", $taux);
                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }
                                        static::recalculerTotaux($lignes, $set);
                                    })
                                    ->helperText('0 = Aucune TVA | 19,25 = Standard'),

                                // ===== EXONÉRATION TVA ✅ AJOUTÉ =====
                                Forms\Components\Toggle::make('exonere_tva')
                                    ->label('Exonération de TVA')
                                    ->helperText('Forcer la TVA à 0% (prioritaire sur TVA Commune)')
                                    ->live(debounce: 500)
                                    ->reactive()
                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                        $set('exonere_tva', (bool) $state);
                                        if ($state) {
                                            $set('tva_commune', 0);
                                        }
                                        if ($state) {
                                            $lignes = $get('lignes') ?? [];
                                            foreach ($lignes as $index => $ligne) {
                                                $set("lignes.$index.taux_tva", 0);
                                                static::recalculerLigne(
                                                    function ($key, $value) use ($set, $index) {
                                                        $set("lignes.$index.$key", $value);
                                                    },
                                                    function ($key) use ($get, $index) {
                                                        return $get("lignes.$index.$key");
                                                    }
                                                );
                                            }
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            if ($state) {
                                                // ✅ Exonéré → TVA = 0, réinitialiser tva_commune
                                                $set('tva_commune', 0);
                                                $set("lignes.$index.taux_tva", 0);
                                            } else {
                                                // ✅ Non exonéré → appliquer tva_commune si définie
                                                $tvaCommune = (float) ($get('tva_commune') ?? 0);
                                                $set("lignes.$index.taux_tva", $tvaCommune);
                                            }

                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }
                                        static::recalculerTotaux($lignes, $set);
                                    }),

                                // ===== IR COMMUN =====
                                Forms\Components\TextInput::make('ir_commun')
                                    ->label('IR Commun (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Ex: 5,5')
                                    ->live(debounce: 500)
                                    ->dehydrated(true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($get('exonere_ir')) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Exonération IR active')
                                                ->warning()
                                                ->body('Désactivez l\'exonération IR pour appliquer ce taux.')
                                                ->send();
                                            return;
                                        }

                                        $taux  = (float) ($state ?? 0);
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            $set("lignes.$index.taux_ir", $taux);
                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }
                                        static::recalculerTotaux($lignes, $set);
                                    })
                                    ->helperText('0 = Aucun IR | 5,5 = Standard')
                                    ->disabled(fn(callable $get) => $get('exonere_ir'))
                                    ->dehydrated(true),

                                // ===== EXONÉRATION IR (inchangé) =====
                                Forms\Components\Toggle::make('exonere_ir')
                                    ->label('Exonération d\'IR')
                                    ->helperText('Forcer l\'IR à 0% (prioritaire sur IR Commun)')
                                    ->live(debounce: 500)
                                    ->reactive()
                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                        $set('exonere_ir', (bool) $state);
                                        if ($state) {
                                            $set('ir_commun', 0);
                                        }
                                        if ($state) {
                                            $lignes = $get('lignes') ?? [];
                                            foreach ($lignes as $index => $ligne) {
                                                $set("lignes.$index.taux_ir", 0);
                                                static::recalculerLigne(
                                                    function ($key, $value) use ($set, $index) {
                                                        $set("lignes.$index.$key", $value);
                                                    },
                                                    function ($key) use ($get, $index) {
                                                        return $get("lignes.$index.$key");
                                                    }
                                                );
                                            }
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            if ($state) {
                                                $set('ir_commun', 0);
                                                $set("lignes.$index.taux_ir", 0);
                                            } else {
                                                $irCommun = (float) ($get('ir_commun') ?? 0);
                                                if ($irCommun > 0) {
                                                    $set("lignes.$index.taux_ir", $irCommun);
                                                } else {
                                                    $typeEngagementId = $get('type_engagement_id');
                                                    $fournisseurId    = $get('fournisseur_id');
                                                    if ($typeEngagementId && $fournisseurId) {
                                                        $typeEngagement = \App\Models\TypeEngagement::find($typeEngagementId);
                                                        $fournisseur    = \App\Models\Fournisseur::with('regimeFiscal')
                                                            ->find($fournisseurId);
                                                        if ($typeEngagement && $fournisseur?->regimeFiscal) {
                                                            $tauxIR = $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                                                            $set("lignes.$index.taux_ir", $tauxIR);
                                                        }
                                                    }
                                                }
                                            }

                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }
                                        static::recalculerTotaux($lignes, $set);
                                    }),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make('Référence')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Référence externe')
                            ->maxLength(100)
                            ->placeholder('Ex: REF-2025-001')
                            ->helperText('Référence du fournisseur ou numéro de dossier externe'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Lignes du Bon de Commande')
                    ->description('Choisissez d\'abord la nomenclature budgétaire qui sera utilisée pour toutes les lignes, puis ajoutez les articles/services.')
                    ->schema([
                        // ===== NOMENCLATURE COMMUNE =====
                        Forms\Components\Select::make('nomenclature_commune_id')
                            ->label('Nomenclature Budgétaire Commune')
                            ->options(function (callable $get) {
                                $budgetId = $get('budget_id');
                                if (!$budgetId) {
                                    return [];
                                }
                                return \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                    ->whereNotNull('nomenclature_id')
                                    ->with('nomenclature')
                                    ->get()
                                    ->filter(fn($lb) => $lb->nomenclature !== null)
                                    ->mapWithKeys(fn($lb) => [
                                        $lb->nomenclature_id => "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} (Dispo: " .
                                            number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->live(debounce: 1000)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                // Propager aux lignes
                                $lignes = $get('lignes') ?? [];
                                foreach ($lignes as $index => $ligne) {
                                    $set("lignes.{$index}.nomenclature_id", $state);
                                }
                            })
                            ->helperText('Cette nomenclature sera assignée à toutes les lignes')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('lignes')
                            ->relationship('lignes')
                            ->schema([

                                // ===== Choix Mercuriale ou Saisie Libre =====
                                Forms\Components\Select::make('reference_mercuriale_id')
                                    ->label('Référence Mercuriale')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        // ✅ Pas de $get ici — récupérer l'exercice actif directement
                                        if (strlen($search) < 3) {
                                            return ['manual' => '➕ Saisie manuelle (tapez au moins 3 caractères)'];
                                        }

                                        $exercice = \App\Models\Exercice::getActif();
                                        if (!$exercice) {
                                            return ['manual' => '➕ Saisie manuelle'];
                                        }

                                        $cacheKey = "mercuriale_search_{$exercice->id}_" . md5($search);

                                        $results = \Cache::remember($cacheKey, now()->addMinutes(5), function () use ($exercice, $search) {
                                            return \App\Models\ReferenceMercuriale::where('exercice_id', $exercice->id)
                                                ->where('actif', true)
                                                ->where(function ($query) use ($search) {
                                                    $query->where('code_reference', 'LIKE', "%{$search}%")
                                                        ->orWhere('designation',    'LIKE', "%{$search}%")
                                                        ->orWhere('rubrique',        'LIKE', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn($ref) => [
                                                    $ref->id => "{$ref->code_reference} - {$ref->designation} — "
                                                        . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA"
                                                ]);
                                        });

                                        return ['manual' => '➕ Saisie manuelle'] + $results->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if ($value === 'manual' || !$value) {
                                            return '➕ Saisie manuelle';
                                        }
                                        return \Cache::remember("mercuriale_label_{$value}", now()->addMinutes(10), function () use ($value) {
                                            $ref = \App\Models\ReferenceMercuriale::find($value);
                                            if (!$ref) return "Référence #{$value}";
                                            return "{$ref->code_reference} - {$ref->designation} — "
                                                . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA";
                                        });
                                    })
                                    ->live(debounce: 800)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if (!$state || $state === 'manual') {
                                            return; // Saisie manuelle — ne rien écraser
                                        }
                                        $ref = \Cache::remember(
                                            "mercuriale_full_{$state}",
                                            now()->addMinutes(10),
                                            fn() => \App\Models\ReferenceMercuriale::find($state)
                                        );
                                        if ($ref) {
                                            $set('designation',        $ref->designation);
                                            $set('unite',              $ref->unite);
                                            $set('prix_unitaire_ht',   $ref->prix_reference);
                                            $set('reference_personnalisee', null);
                                            // ✅ Recalculer immédiatement après remplissage
                                            static::recalculerLigne($set, $get);
                                        }
                                    })
                                    ->helperText('Tapez au moins 3 caractères pour rechercher')
                                    ->columnSpan(2),

                                // ===== Référence personnalisée (pour saisie manuelle) =====
                                Forms\Components\TextInput::make('reference_personnalisee')
                                    ->label('Réf. perso')
                                    ->maxLength(100)
                                    ->placeholder('Ex: REF-001')
                                    ->visible(fn(callable $get) => $get('reference_mercuriale_id') === 'manual' || !$get('reference_mercuriale_id'))
                                    ->columnSpan(1),

                                // ===== Désignation et Observations =====
                                Forms\Components\TextInput::make('designation')
                                    ->label('Désignation')
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('observations')
                                    ->label('Observations')
                                    ->rows(2)
                                    ->columnSpan(3),

                                // ===== Quantités, Prix, Taxes =====
                                Forms\Components\Grid::make(6)
                                    ->schema([
                                        Forms\Components\TextInput::make('quantite')
                                            ->label('Qté')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, callable $set, callable $get) => static::recalculerLigne($set, $get)),

                                        Forms\Components\Select::make('unite')
                                            ->label('Unité')
                                            ->options([
                                                'pièce' => 'Pièce',
                                                'lot' => 'Lot',
                                                'kg' => 'Kg',
                                                'litre' => 'L',
                                                'mètre' => 'M',
                                                'heure' => 'H',
                                                'jour' => 'J',
                                                'forfait' => 'Forfait',
                                            ])
                                            ->required()
                                            ->default('pièce')
                                            ->searchable(),

                                        Forms\Components\TextInput::make('prix_unitaire_ht')
                                            ->label('P.U HT')
                                            ->numeric()
                                            ->required()
                                            ->prefix('FCFA')
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, callable $set, callable $get) => static::recalculerLigne($set, $get)),

                                        Forms\Components\TextInput::make('taux_tva')
                                            ->label('TVA %')
                                            ->numeric()
                                            ->default(fn(callable $get) => (float) ($get('../../tva_commune') ?? 19.25))
                                            ->suffix('%')
                                            ->disabled(fn(callable $get) => (bool) $get('../../exonere_tva'))
                                            ->dehydrated(true)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(
                                                fn($state, callable $set, callable $get) =>
                                                static::recalculerLigne($set, $get)
                                            )
                                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                if ($get('../../exonere_tva')) {
                                                    $set('taux_tva', 0);
                                                }
                                            }),

                                        Forms\Components\TextInput::make('taux_ir')
                                            ->label('IR %')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, callable $set, callable $get) => static::recalculerLigne($set, $get))
                                            ->helperText('IR spécifique (0 = auto)')
                                            ->default(fn(callable $get) => (float) ($get('../../ir_commun') ?? 0))
                                            ->disabled(fn(callable $get) => (bool) $get('../../exonere_ir'))
                                            ->dehydrated(true)
                                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                if ($get('../../exonere_ir')) {
                                                    $set('taux_ir', 0);
                                                }
                                            }),

                                        Forms\Components\Placeholder::make('net_a_payer')
                                            ->label('Net à payer')
                                            ->content(function (callable $get) {
                                                $netAPercevoir = (float) ($get('net_a_payer') ?? 0); // ← CHANGÉ
                                                return number_format($netAPercevoir, 0, ',', ' ') . ' FCFA';
                                            }),
                                    ]),

                                // ===== Champs cachés =====
                                Forms\Components\Hidden::make('montant_ht')->default(0),
                                Forms\Components\Hidden::make('montant_tva')->default(0),
                                Forms\Components\Hidden::make('montant_ir')->default(0),
                                Forms\Components\Hidden::make('montant_tsr')->default(0),
                                Forms\Components\Hidden::make('montant_ttc')->default(0),
                                Forms\Components\Hidden::make('net_a_payer')->default(0),
                                Forms\Components\Hidden::make('nomenclature_id')
                                    ->default(function (callable $get) {
                                        return $get('../../nomenclature_commune_id');
                                    }),

                                Forms\Components\Section::make('Suivi des quantités')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([

                                            // Quantité commandée = lecture seule, miroir de quantite
                                            Forms\Components\Placeholder::make('quantite_commandee_affichee')
                                                ->label('Qté commandée')
                                                ->content(fn(callable $get) => (int) ($get('quantite') ?? 0)),

                                            // Quantité livrée — éditable
                                            Forms\Components\TextInput::make('quantite_livree')
                                                ->label('Qté livrée')
                                                ->numeric()
                                                ->default(0)
                                                ->minValue(0)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $commandee = (float) ($get('quantite')      ?? 0);
                                                    $livree    = (float) ($state               ?? 0);

                                                    // Bloquer si livré > commandé
                                                    if ($livree > $commandee) {
                                                        $set('quantite_livree', $commandee);
                                                        $livree = $commandee;
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Quantité livrée limitée')
                                                            ->warning()
                                                            ->body("La quantité livrée ne peut pas dépasser la quantité commandée ({$commandee}).")
                                                            ->send();
                                                    }

                                                    $set('quantite_restante', max(0, $commandee - $livree));
                                                })
                                                ->suffix(fn(callable $get) => '/ ' . (int) ($get('quantite') ?? 0))
                                                ->helperText('Ne peut pas dépasser la quantité commandée'),

                                            // Quantité restante — calculée, lecture seule
                                            Forms\Components\Placeholder::make('quantite_restante_affichee')
                                                ->label('Qté restante')
                                                ->content(function (callable $get) {
                                                    $commandee = (float) ($get('quantite')        ?? 0);
                                                    $livree    = (float) ($get('quantite_livree') ?? 0);
                                                    $restante  = max(0, $commandee - $livree);
                                                    $couleur   = $restante === 0.0 ? '✅' : ($livree > 0 ? '🔄' : '⏳');
                                                    return "{$couleur} {$restante}";
                                                }),
                                        ]),
                                    ])
                                    ->collapsible()
                                    ->collapsed(fn(callable $get) => (float) ($get('quantite_livree') ?? 0) === 0.0)
                                    ->columnSpanFull(),

                                // ✅ Conserver le Hidden pour la persistance en base
                                Forms\Components\Hidden::make('quantite_restante')
                                    ->default(fn(callable $get) => $get('quantite') ?? 0),

                                // ===== Récapitulatif =====
                                Forms\Components\Placeholder::make('recap_montants')
                                    ->label('Récapitulatif')
                                    ->content(function (callable $get) {
                                        $montantHT = (float) ($get('montant_ht') ?? 0);
                                        $montantTVA = (float) ($get('montant_tva') ?? 0);
                                        $montantIR = (float) ($get('montant_ir') ?? 0);
                                        $montantTSR = (float) ($get('montant_tsr') ?? 0);
                                        $montantTTC = (float) ($get('montant_ttc') ?? 0);
                                        $netAPercevoir = (float) ($get('net_a_payer') ?? 0);

                                        return sprintf(
                                            "HT: %s | TVA: %s | TTC: %s | IR: %s | Net: %s",
                                            number_format($montantHT, 0, ',', ' '),
                                            number_format($montantTVA, 0, ',', ' '),
                                            number_format($montantTTC, 0, ',', ' '),
                                            number_format($montantIR, 0, ',', ' '),
                                            number_format($montantTSR, 0, ',', ' '),
                                            number_format($netAPercevoir, 0, ',', ' ')
                                        );
                                    })
                                    ->columnSpan(3),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('➕ Ajouter une ligne')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['designation'] ?? 'Nouvelle ligne')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                static::recalculerTotaux($state, $set);
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, callable $get): array {
                                // 1. Nomenclature
                                $nomenclatureCommuneId = $get('nomenclature_commune_id');
                                if ($nomenclatureCommuneId && empty($data['nomenclature_id'])) {
                                    $data['nomenclature_id'] = $nomenclatureCommuneId;
                                }

                                // 2. TVA selon exonération
                                if (!isset($data['taux_tva']) || $data['taux_tva'] === null) {
                                    $data['taux_tva'] = $get('exonere_tva') ? 0 : 19.25;
                                }

                                // 3. IR selon exonération
                                if (!isset($data['taux_ir']) || $data['taux_ir'] === null) {
                                    $data['taux_ir'] = $get('exonere_ir') ? 0 : 5.5;
                                }

                                return $data;
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, callable $get): array {
                                // Lors du chargement, s'assurer que la nomenclature commune est définie
                                if (empty($data['nomenclature_id'])) {
                                    $nomenclatureCommuneId = $get('nomenclature_commune_id');
                                    if ($nomenclatureCommuneId) {
                                        $data['nomenclature_id'] = $nomenclatureCommuneId;
                                    }
                                }
                                return $data;
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null && $record->lignes()->count() > 0),
            ])
            ->statePath('data')
            ->model(BonCommande::class);
    }

    /**
     * Recalculer une ligne (montants HT, TVA, IR, TTC, net à payer)
     */
    protected static function recalculerLigne(callable $set, callable $get): void
    {
        $quantite       = (float) ($get('quantite')        ?? 0);
        $prixUnitaireHT = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTVA        = (float) ($get('taux_tva')         ?? 19.25);
        $tauxIR         = (float) ($get('taux_ir')          ?? 0);

        // 1. MHT
        $montantHT  = $quantite * $prixUnitaireHT;
        $set('montant_ht', round($montantHT, 2));

        // 2. TVA
        $montantTVA = ($montantHT * $tauxTVA) / 100;
        $set('montant_tva', round($montantTVA, 2));

        // 3. TTC
        $montantTTC = $montantHT + $montantTVA;
        $set('montant_ttc', round($montantTTC, 2));

        // 4. IR
        $montantIR = 0;
        $exonereIR = $get('../../exonere_ir') ?? false;

        if ($exonereIR) {
            $montantIR = 0;
            $set('taux_ir', 0);
        } elseif ($tauxIR > 0) {
            $montantIR = ($montantHT * $tauxIR) / 100;
        } else {
            $typeEngagementId = $get('../../type_engagement_id');
            $fournisseurId    = $get('../../fournisseur_id');
            if ($typeEngagementId && $fournisseurId && $montantHT > 0) {
                $typeEngagement = \App\Models\TypeEngagement::find($typeEngagementId);
                $fournisseur    = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);
                if ($typeEngagement && $fournisseur && $fournisseur->regimeFiscal) {
                    $tauxIRAuto = $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                    $montantIR  = ($montantHT * $tauxIRAuto) / 100;
                    $set('taux_ir', $tauxIRAuto);
                }
            }
        }

        $set('montant_ir', round($montantIR, 2));

        // ✅ 5. NET A PAYER = MHT arrondi - IR arrondi (valeurs telles qu'affichées)
        $montantHTArrondi = (int) number_format($montantHT,  0, '.', '');
        $montantIRArrondi = (int) number_format($montantIR,  0, '.', '');
        $netAPayer        = $montantHTArrondi - $montantIRArrondi;
        $set('net_a_payer', $netAPayer);

        // 6. Quantité restante
        $quantiteLivree = (float) ($get('quantite_livree') ?? 0);
        $set('quantite_restante', max(0, $quantite - $quantiteLivree));

        // 7. Recalcul totaux BC
        $lignes = $get('../../lignes') ?? [];
        static::recalculerTotaux($lignes, function ($key, $value) use ($set) {
            $set("../../{$key}", $value);
        });
    }

    /**
     * Recalculer une ligne (IR, montants, etc.)
     */
    protected static function recalculerTotaux(?array $lignes, callable $set): void
    {
        if (!$lignes) return;

        $totalHT        = 0;
        $totalTVA       = 0;
        $totalIR        = 0;
        $totalTTC       = 0;
        $totalNetAPayer = 0;

        // ✅ Sommer les valeurs déjà arrondies (comme affichées dans chaque ligne)
        foreach ($lignes as $ligne) {
            $totalHT  += (int) number_format((float)($ligne['montant_ht']  ?? 0), 0, '.', '');
            $totalTVA += (int) number_format((float)($ligne['montant_tva'] ?? 0), 0, '.', '');
            $totalIR  += (int) number_format((float)($ligne['montant_ir']  ?? 0), 0, '.', '');
            $totalTTC += (int) number_format((float)($ligne['montant_ttc'] ?? 0), 0, '.', '');
        }

        // ✅ NET A PAYER = Total HT arrondi - Total IR arrondi
        $totalNetAPayer = $totalHT - $totalIR;

        $set('montant_ht',  $totalHT);
        $set('montant_tva', $totalTVA);
        $set('montant_ir',  $totalIR);
        $set('montant_ttc', $totalTTC);
        $set('net_a_payer', $totalNetAPayer);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // ✅ withoutGlobalScope — inclut tous les exercices (actif + clôturé)
        $query = parent::getEloquentQuery()
            ->withoutGlobalScope('exercice')
            ->with('exercice');

        $user = auth()->user();

        if ($user && $user->hasRole('super_admin')) {
            return $query;
        }

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereDoesntHave('transmissions', function ($transmission) {
                    $transmission->where('statut', 'en_attente');
                })
                ->orWhereHas('transmissions', function ($transmission) use ($user) {
                    $transmission->where('statut', 'en_attente')
                        ->where('destinataire_id', $user->id);
                });
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([

                Tables\Columns\TextColumn::make('numero')
                    ->label('N° BC')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->searchable()->limit(30)->wrap(),

                Tables\Columns\TextColumn::make('serviceDemandeur.nom')
                    ->label('Service')->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date')->date('d/m/Y')->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('Montant HT')->money('XAF')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_tva')
                    ->label('TVA')->money('XAF')->sortable()->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->sortable()->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')->money('XAF')->sortable()->weight('bold')->color('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')
                    ->counts('lignes')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Indicateur de livraison global
                Tables\Columns\TextColumn::make('avancement_livraison')
                    ->label('Livraison')
                    ->getStateUsing(function ($record) {
                        $lignes      = $record->lignes;
                        $commandee   = $lignes->sum('quantite');
                        $livree      = $lignes->sum('quantite_livree');

                        if ($commandee <= 0) return '—';

                        $pct = round(($livree / $commandee) * 100);
                        return "{$pct}% ({$livree}/{$commandee})";
                    })
                    ->badge()
                    ->color(function ($record) {
                        $lignes    = $record->lignes;
                        $commandee = $lignes->sum('quantite');
                        $livree    = $lignes->sum('quantite_livree');

                        if ($commandee <= 0) return 'gray';
                        $pct = ($livree / $commandee) * 100;
                        return match (true) {
                            $pct >= 100 => 'success',
                            $pct >= 50  => 'warning',
                            $pct > 0    => 'info',
                            default     => 'gray',
                        };
                    })
                    ->toggleable(),

                // ✅ Correction — champ réel net_a_percevoir
                Tables\Columns\TextColumn::make('net_a_percevoir')
                    ->label('Net à Percevoir')->money('XAF')->sortable()->weight('bold')->color('primary')
                    ->description(
                        fn($record) =>
                        "HT: " . number_format($record->montant_ht, 0, ',', ' ') .
                            " | IR: " . number_format($record->montant_ir, 0, ',', ' ')
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_total_impots')
                    ->label('Total Impôts')
                    ->getStateUsing(fn($record) => $record->calculerMontantTotalImpots())
                    ->money('XAF')->sortable()->color('warning')
                    ->description(function ($record) {
                        $details = [];
                        if ($record->montant_tva > 0) $details[] = "TVA: " . number_format($record->montant_tva, 0, ',', ' ');
                        if ($record->montant_ir  > 0) $details[] = "IR: "  . number_format($record->montant_ir,  0, ',', ' ');
                        if ($record->montant_tsr > 0) $details[] = "TSR: " . number_format($record->montant_tsr, 0, ',', ' ');
                        if ($record->montant_cnps > 0) $details[] = "CNPS: " . number_format($record->montant_cnps, 0, ',', ' ');
                        return implode(' | ', $details);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning'   => 'valide',
                        'primary'   => 'engage',
                        'info'      => 'en_cours',
                        'success'   => fn($state) => in_array($state, ['livre_partiellement', 'livre']),
                        'danger'    => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon'          => 'Brouillon',
                        'valide'             => 'Validé',
                        'engage'             => 'Engagé',
                        'en_cours'           => 'En cours',
                        'livre_partiellement' => 'Livré part.',
                        'livre'              => 'Livré',
                        'annule'             => 'Annulé',
                        default              => $state,
                    }),

                Tables\Columns\IconColumn::make('engage')
                    ->label('Engagé')->boolean()->trueColor('success')->falseColor('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('typeEngagement.libelle')
                    ->label('Type')->badge()
                    ->color(fn($record) => match ($record->typeEngagement?->code) {
                        'BC' => 'success',
                        'LC' => 'warning',
                        'MA' => 'primary',
                        'DL', 'DM' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')->searchable()->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('engagement.reference_document')
                    ->label('N° Engagement')->searchable()->badge()->color('success')
                    ->icon('heroicon-o-banknotes')->placeholder('-')
                    ->visible(fn($record) => $record && $record->engage && $record->engagement)
                    ->description(
                        fn($record) =>
                        $record?->engagement && $record->date_engagement
                            ? 'Engagé le ' . $record->date_engagement->format('d/m/Y')
                            : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('transmission_status')
                    ->label('Transmission')
                    ->getStateUsing(function ($record) {
                        $transmission = $record->transmissions()
                            ->where('statut', 'en_attente')->latest()->first();
                        if (!$transmission) return null;
                        if ($transmission->destinataire_id === auth()->id()) return 'À traiter';
                        if ($transmission->expediteur_id === auth()->id())
                            return 'En attente chez ' . $transmission->destinataire->name;
                        return 'Transmis à ' . $transmission->destinataire->name;
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state === 'À traiter'                       => 'warning',
                        str_starts_with($state ?? '', 'En attente') => 'info',
                        default                                      => 'gray',
                    })
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
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
                            'today' => $query->whereDate('date_emission', today()),
                            'yesterday' => $query->whereDate('date_emission', today()->subDay()),
                            'this_week' => $query->whereBetween('date_emission', [
                                now()->startOfWeek(),
                                now()->endOfWeek()
                            ]),
                            'last_week' => $query->whereBetween('date_emission', [
                                now()->subWeek()->startOfWeek(),
                                now()->subWeek()->endOfWeek()
                            ]),
                            'this_month' => $query->whereMonth('date_emission', now()->month)
                                ->whereYear('date_emission', now()->year),
                            'last_month' => $query->whereMonth('date_emission', now()->subMonth()->month)
                                ->whereYear('date_emission', now()->subMonth()->year),
                            'this_quarter' => $query->whereBetween('date_emission', [
                                now()->startOfQuarter(),
                                now()->endOfQuarter()
                            ]),
                            'last_quarter' => $query->whereBetween('date_emission', [
                                now()->subQuarter()->startOfQuarter(),
                                now()->subQuarter()->endOfQuarter()
                            ]),
                            'this_year' => $query->whereYear('date_emission', now()->year),
                            'last_year' => $query->whereYear('date_emission', now()->subYear()->year),
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

                Tables\Filters\Filter::make('date_emission')
                    ->form([
                        Forms\Components\DatePicker::make('date_emission_from')
                            ->label('Date d\'émission du')
                            ->placeholder('JJ/MM/AAAA'),
                        Forms\Components\DatePicker::make('date_emission_until')
                            ->label('Date d\'émission au')
                            ->placeholder('JJ/MM/AAAA'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_emission_from'], fn($q, $date) =>
                            $q->whereDate('date_emission', '>=', $date))
                            ->when($data['date_emission_until'], fn($q, $date) =>
                            $q->whereDate('date_emission', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_emission_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Émis depuis le ' . \Carbon\Carbon::parse($data['date_emission_from'])->format('d/m/Y'))
                                ->removeField('date_emission_from');
                        }

                        if ($data['date_emission_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Émis jusqu\'au ' . \Carbon\Carbon::parse($data['date_emission_until'])->format('d/m/Y'))
                                ->removeField('date_emission_until');
                        }

                        return $indicators;
                    }),

                Tables\Filters\Filter::make('mes_bons')
                    ->label('📁 Tous mes bons')
                    ->query(function ($query) {
                        return $query->where('created_by', auth()->id());
                    })
                    ->toggle()
                    ->default(false)
                    ->indicateUsing(fn() => '📁 Tous les bons (créés par moi)'),

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

                Tables\Filters\Filter::make('à_traiter')
                    ->label('📌 A traiter par moi')
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
                    ->default(true)
                    ->indicateUsing(fn() => '📌 Bons nécessitant mon action'),

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

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide' => 'Validé',
                        'engage' => 'Engagé',
                        'en_cours' => 'En cours',
                        'livre_partiellement' => 'Livré partiellement',
                        'livre' => 'Livré',
                        'annule' => 'Annulé',
                    ]),

                Tables\Filters\TernaryFilter::make('engage')
                    ->label('Engagé')
                    ->placeholder('Tous')
                    ->trueLabel('Engagés')
                    ->falseLabel('Non engagés'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    ...WorkflowActions::make(
                        avecEngagement: true,
                        pdfServiceClass: BonCommandePdfService::class,
                        pdfRouteName: 'bons-commande.pdf.preview',
                        avecModalEngagement: true
                    ),

                    Tables\Actions\Action::make('generer_expression_besoin')
                        ->label('Générer Expression de Besoin')
                        ->icon('heroicon-o-document-plus')
                        ->color('info')
                        ->visible(
                            fn($record) =>
                            $record->engage
                                && !$record->expression_besoin_id
                                && auth()->user()?->can('generer_expression_besoin_bon_commande')
                        )
                        ->form(function ($record) {
                            $record->load('lignes');
                            return [
                                Forms\Components\Placeholder::make('info')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        '<div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:.5rem;padding:.75rem;font-size:.85rem;">'
                                            . '<strong>ℹ️ Génération automatique</strong><br>'
                                            . 'Toutes les lignes du bon de commande seront reprises. '
                                            . 'Si un article n\'existe pas encore dans le catalogue Comptabilité Matières, '
                                            . 'il sera créé automatiquement avec les informations minimales (désignation, unité, prix). '
                                            . 'Le comptable-matières pourra compléter la fiche article ultérieurement.'
                                            . '</div>'
                                    ))
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('apercu_lignes')
                                    ->label('Lignes qui seront reprises')
                                    ->content(function () use ($record) {
                                        $html = '<table style="width:100%;border-collapse:collapse;font-size:.82rem;">'
                                            . '<thead><tr style="background:#f1f5f9;">'
                                            . '<th style="border:1px solid #cbd5e1;padding:4px 8px;text-align:left;">Désignation</th>'
                                            . '<th style="border:1px solid #cbd5e1;padding:4px 8px;text-align:center;">Unité</th>'
                                            . '<th style="border:1px solid #cbd5e1;padding:4px 8px;text-align:center;">Quantité</th>'
                                            . '<th style="border:1px solid #cbd5e1;padding:4px 8px;text-align:right;">P.U HT</th>'
                                            . '</tr></thead><tbody>';

                                        foreach ($record->lignes as $l) {
                                            $html .= '<tr>'
                                                . '<td style="border:1px solid #cbd5e1;padding:4px 8px;">' . htmlspecialchars($l->designation) . '</td>'
                                                . '<td style="border:1px solid #cbd5e1;padding:4px 8px;text-align:center;">' . htmlspecialchars($l->unite ?? '—') . '</td>'
                                                . '<td style="border:1px solid #cbd5e1;padding:4px 8px;text-align:center;">' . (int) $l->quantite . '</td>'
                                                . '<td style="border:1px solid #cbd5e1;padding:4px 8px;text-align:right;">' . number_format($l->prix_unitaire_ht, 0, ',', ' ') . ' FCFA</td>'
                                                . '</tr>';
                                        }

                                        $html .= '</tbody></table>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('comptable_matieres_id')
                                    ->label('Comptable-matières responsable')
                                    ->options(function () {
                                        $parRole = \App\Models\User::role('comptable_matieres')->pluck('name', 'id');
                                        if ($parRole->isNotEmpty()) return $parRole;

                                        $parPermission = \App\Models\User::permission('valider_expression_besoin')->pluck('name', 'id');
                                        if ($parPermission->isNotEmpty()) return $parPermission;

                                        return \App\Models\User::orderBy('name')->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable(),
                            ];
                        })
                        ->modalHeading('Générer une Expression de Besoin depuis ce Bon de Commande')
                        ->modalWidth('2xl')
                        ->action(function ($record, array $data) {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                                $record->load('lignes');

                                // ✅ Mapper les unités BC vers les unités Article
                                $mapUnites = [
                                    'pièce'   => 'pièce',
                                    'piece'   => 'pièce',
                                    'lot'     => 'lot',
                                    'kg'      => 'kg',
                                    'kilogramme' => 'kg',
                                    'litre'   => 'litre',
                                    'l'          => 'litre',
                                    'mètre'   => 'mètre',
                                    'metre'      => 'mètre',
                                    'heure'   => 'heure',
                                    'h'          => 'heure',
                                    'jour'    => 'jour',
                                    'j'          => 'jour',
                                    'forfait' => 'forfait',
                                    'boîte'   => 'lot',
                                    'boite'      => 'lot',
                                ];

                                // ✅ Créer l'Expression de Besoin
                                $eb = \App\Models\ExpressionBesoin::create([
                                    'exercice_id'            => $record->exercice_id,
                                    'service_demandeur_id'   => $record->service_demandeur_id,
                                    'service_demandeur'      => $record->serviceDemandeur?->nom ?? 'Service',
                                    'responsable_service_id' => auth()->id(),
                                    'comptable_matieres_id'  => $data['comptable_matieres_id'],
                                    'objet'                  => $record->objet,
                                    'statut'                 => 'signe_dg',
                                    'date_expression'        => now()->toDateString(),
                                    'date_validation'        => now()->toDateString(),
                                    'signe_par_id'           => auth()->id(),
                                    'date_signature_dg'      => now(),
                                    'created_by'             => auth()->id(),
                                ]);

                                $compte = 0;

                                foreach ($record->lignes as $ligneBc) {
                                    $designation = trim($ligneBc->designation ?? '');
                                    if (empty($designation)) continue;

                                    // ✅ Chercher l'article existant (insensible à la casse)
                                    $article = \App\Models\Article::whereRaw(
                                        'LOWER(TRIM(designation)) = ?',
                                        [strtolower($designation)]
                                    )->first();

                                    // ✅ Sinon : créer automatiquement avec infos minimales
                                    if (!$article) {
                                        // Résoudre ou créer l'unité de mesure
                                        $uniteLibelle = $mapUnites[strtolower(trim($ligneBc->unite ?? ''))] ?? 'pièce';

                                        $uniteMesure = \App\Models\UniteMesure::firstOrCreate(
                                            ['libelle' => $uniteLibelle],
                                            ['actif'   => true]
                                        );

                                        $article = \App\Models\Article::create([
                                            'designation'        => $designation,
                                            'type'               => 'consomptible',
                                            'unite_mesure'       => $uniteLibelle,
                                            'unite_mesure_id'    => $uniteMesure->id,
                                            'prix_unitaire_moyen' => (float) ($ligneBc->prix_unitaire_ht ?? 0),
                                            'actif'              => true,
                                            'seuil_alerte'       => 0,
                                        ]);

                                        \Log::info("Article créé automatiquement depuis BC {$record->numero}", [
                                            'designation' => $designation,
                                            'article_id'  => $article->id,
                                        ]);
                                    }

                                    // ✅ Créer la ligne d'expression de besoin
                                    \App\Models\LigneExpressionBesoin::create([
                                        'expression_besoin_id' => $eb->id,
                                        'article_id'           => $article->id,
                                        'quantite_demandee'    => (int) ($ligneBc->quantite ?? 1),
                                        'prix_unitaire_estime' => (float) ($ligneBc->prix_unitaire_ht ?? 0),
                                        'justification'        => "Issu du Bon de Commande {$record->numero}",
                                        'bon_commande_id'      => $record->id,
                                        'ordre'                => $ligneBc->numero_ligne ?? ($compte + 1),
                                    ]);

                                    $compte++;
                                }

                                $record->update(['expression_besoin_id' => $eb->id]);

                                Notification::make()
                                    ->title("✅ Expression {$eb->numero} générée avec {$compte} ligne(s)")
                                    ->success()
                                    ->body(
                                        $compte > 0
                                            ? "Les articles nouveaux ont été créés automatiquement dans le catalogue. Le comptable-matières peut les compléter."
                                            : "Aucune ligne trouvée sur ce bon de commande."
                                    )
                                    ->send();
                            });
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
        return [
            RelationManagers\LignesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBonCommandes::route('/'),
            'create' => Pages\CreateBonCommande::route('/create'),
            'edit' => Pages\EditBonCommande::route('/{record}/edit'),
            'view' => Pages\ViewBonCommande::route('/{record}'),
        ];
    }
}
