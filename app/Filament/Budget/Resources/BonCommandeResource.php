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

class BonCommandeResource extends Resource
{
    protected static ?string $model = BonCommande::class;

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Bons de Commande';

    protected static ?string $modelLabel = 'Bon de Commande';

    protected static ?string $pluralModelLabel = 'Bons de Commande';

    protected static ?int $navigationSort = 1;

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

                // Forms\Components\Toggle::make('exonere_tva')
                //     ->label('Exonération de TVA')
                //     ->live(debounce: 1000)
                //     ->reactive()
                //     ->afterStateHydrated(function ($state, callable $set, callable $get) {
                //         // Forcer l'état booléen
                //         $set('exonere_tva', (bool) $state);

                //         // Recalculer toutes les lignes lors du chargement
                //         if ($state) {
                //             $lignes = $get('lignes') ?? [];
                //             foreach ($lignes as $index => $ligne) {
                //                 $set("lignes.$index.taux_tva", 0);
                //                 // Recalculer la ligne
                //                 static::recalculerLigne(
                //                     function ($key, $value) use ($set, $index) {
                //                         $set("lignes.$index.$key", $value);
                //                     },
                //                     function ($key) use ($get, $index) {
                //                         return $get("lignes.$index.$key");
                //                     }
                //                 );
                //             }
                //         }
                //     })
                //     ->afterStateUpdated(function ($state, callable $set, callable $get) {
                //         $lignes = $get('lignes') ?? [];

                //         foreach ($lignes as $index => $ligne) {
                //             $set("lignes.$index.taux_tva", $state ? 0 : 19.25);
                //             // Recalculer immédiatement chaque ligne
                //             static::recalculerLigne(
                //                 function ($key, $value) use ($set, $index) {
                //                     $set("lignes.$index.$key", $value);
                //                 },
                //                 function ($key) use ($get, $index) {
                //                     return $get("lignes.$index.$key");
                //                 }
                //             );
                //         }

                //         // Recalculer les totaux
                //         static::recalculerTotaux($lignes, $set);
                //     }),

                // Forms\Components\Toggle::make('exonere_ir')
                //     ->label('Exonération d\'IR')
                //     ->helperText('Forcer l\'IR à 0% (même si le fournisseur est assujetti)')
                //     ->live(debounce: 1000)
                //     ->reactive()
                //     ->afterStateHydrated(function ($state, callable $set, callable $get) {
                //         // Forcer l'état booléen
                //         $set('exonere_ir', (bool) $state);

                //         // Recalculer toutes les lignes lors du chargement
                //         if ($state) {
                //             $lignes = $get('lignes') ?? [];
                //             foreach ($lignes as $index => $ligne) {
                //                 $set("lignes.$index.taux_ir", 0);
                //                 // Recalculer la ligne
                //                 static::recalculerLigne(
                //                     function ($key, $value) use ($set, $index) {
                //                         $set("lignes.$index.$key", $value);
                //                     },
                //                     function ($key) use ($get, $index) {
                //                         return $get("lignes.$index.$key");
                //                     }
                //                 );
                //             }
                //         }
                //     })
                //     ->afterStateUpdated(function ($state, callable $set, callable $get) {
                //         $lignes = $get('lignes') ?? [];

                //         foreach ($lignes as $index => $ligne) {
                //             if ($state) {
                //                 // Si exonéré, forcer IR à 0
                //                 $set("lignes.$index.taux_ir", 0);
                //             } else {
                //                 // Si non exonéré, recalculer l'IR selon le régime fiscal
                //                 $typeEngagementId = $get('type_engagement_id');
                //                 $fournisseurId = $get('fournisseur_id');

                //                 if ($typeEngagementId && $fournisseurId) {
                //                     $typeEngagement = \App\Models\TypeEngagement::find($typeEngagementId);
                //                     $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);

                //                     if ($typeEngagement && $fournisseur && $fournisseur->regimeFiscal) {
                //                         $tauxIR = $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                //                         $set("lignes.$index.taux_ir", $tauxIR);
                //                     }
                //                 }
                //             }

                //             // Recalculer immédiatement chaque ligne
                //             static::recalculerLigne(
                //                 function ($key, $value) use ($set, $index) {
                //                     $set("lignes.$index.$key", $value);
                //                 },
                //                 function ($key) use ($get, $index) {
                //                     return $get("lignes.$index.$key");
                //                 }
                //             );
                //         }

                //         // Recalculer les totaux
                //         static::recalculerTotaux($lignes, $set);
                //     }),
                Forms\Components\Section::make('Taux Communs et Exonérations')
                    ->description('Appliquez des taux communs à toutes les lignes ou gérez les exonérations')
                    ->schema([
                        Forms\Components\Grid::make(3)
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
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $taux = (float) ($state ?? 0);
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            $set("lignes.$index.taux_tva", $taux);

                                            // Recalculer la ligne
                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }

                                        // Recalculer les totaux
                                        static::recalculerTotaux($lignes, $set);
                                    })
                                    ->helperText('0 = Aucune TVA | 19,25 = Standard'),

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
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // Vérifier d'abord si l'exonération IR est active
                                        $exonereIr = $get('exonere_ir');
                                        if ($exonereIr) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Exonération IR active')
                                                ->warning()
                                                ->body('L\'exonération IR est activée. Désactivez-la pour appliquer ce taux.')
                                                ->send();
                                            return;
                                        }

                                        $taux = (float) ($state ?? 0);
                                        $lignes = $get('lignes') ?? [];

                                        foreach ($lignes as $index => $ligne) {
                                            $set("lignes.$index.taux_ir", $taux);

                                            // Recalculer la ligne
                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }

                                        // Recalculer les totaux
                                        static::recalculerTotaux($lignes, $set);
                                    })
                                    ->helperText('0 = Aucun IR | 5,5 = Standard')
                                    ->disabled(fn(callable $get) => $get('exonere_ir'))
                                    ->dehydrated(true),

                                // ===== EXONÉRATION IR (Toggle existant) =====
                                Forms\Components\Toggle::make('exonere_ir')
                                    ->label('Exonération d\'IR')
                                    ->helperText('Forcer l\'IR à 0% (prioritaire sur IR Commun)')
                                    ->live(debounce: 500)
                                    ->reactive()
                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                        // Forcer l'état booléen
                                        $set('exonere_ir', (bool) $state);

                                        // Si exonéré, désactiver le champ IR Commun
                                        if ($state) {
                                            $set('ir_commun', 0);
                                        }

                                        // Recalculer toutes les lignes lors du chargement
                                        if ($state) {
                                            $lignes = $get('lignes') ?? [];
                                            foreach ($lignes as $index => $ligne) {
                                                $set("lignes.$index.taux_ir", 0);
                                                // Recalculer la ligne
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
                                                // ✅ Si exonéré, forcer IR à 0 ET réinitialiser ir_commun
                                                $set('ir_commun', 0);
                                                $set("lignes.$index.taux_ir", 0);
                                            } else {
                                                // ✅ Si non exonéré, vérifier dans cet ordre :
                                                // 1. IR Commun s'il existe
                                                // 2. Sinon calculer selon le régime fiscal

                                                $irCommun = (float) ($get('ir_commun') ?? 0);

                                                if ($irCommun > 0) {
                                                    // Appliquer l'IR commun
                                                    $set("lignes.$index.taux_ir", $irCommun);
                                                } else {
                                                    // Recalculer selon le régime fiscal
                                                    $typeEngagementId = $get('type_engagement_id');
                                                    $fournisseurId = $get('fournisseur_id');

                                                    if ($typeEngagementId && $fournisseurId) {
                                                        $typeEngagement = \App\Models\TypeEngagement::find($typeEngagementId);
                                                        $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);

                                                        if ($typeEngagement && $fournisseur && $fournisseur->regimeFiscal) {
                                                            $tauxIR = $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                                                            $set("lignes.$index.taux_ir", $tauxIR);
                                                        }
                                                    }
                                                }
                                            }

                                            // Recalculer immédiatement chaque ligne
                                            static::recalculerLigne(
                                                function ($key, $value) use ($set, $index) {
                                                    $set("lignes.$index.$key", $value);
                                                },
                                                function ($key) use ($get, $index) {
                                                    return $get("lignes.$index.$key");
                                                }
                                            );
                                        }

                                        // Recalculer les totaux
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
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        $exerciceId = $get('../../exercice_id');

                                        if (!$exerciceId || strlen($search) < 3) {
                                            return ['manual' => '➕ Saisie manuelle (tapez au moins 3 caractères)'];
                                        }

                                        // ✅ Cache pour 5 minutes
                                        $cacheKey = "mercuriale_search_{$exerciceId}_" . md5($search);

                                        return \Cache::remember($cacheKey, now()->addMinutes(5), function () use ($exerciceId, $search) {
                                            $results = \App\Models\ReferenceMercuriale::where('exercice_id', $exerciceId)
                                                ->where('actif', true)
                                                ->where(function ($query) use ($search) {
                                                    $query->where('code_reference', 'LIKE', "%{$search}%")
                                                        ->orWhere('designation', 'LIKE', "%{$search}%")
                                                        ->orWhere('rubrique', 'LIKE', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn($ref) => [
                                                    $ref->id => "{$ref->code_reference} - {$ref->designation} - " .
                                                        number_format($ref->prix_reference, 0, ',', ' ') . " FCFA"
                                                ]);

                                            return ['manual' => '➕ Saisie manuelle'] + $results->toArray();
                                        });
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if ($value === 'manual') {
                                            return '➕ Saisie manuelle';
                                        }

                                        // Cache aussi la récupération du label
                                        return \Cache::remember("mercuriale_label_{$value}", now()->addMinutes(10), function () use ($value) {
                                            $ref = \App\Models\ReferenceMercuriale::find($value);

                                            if (!$ref) {
                                                return "Référence #{$value}";
                                            }

                                            return "{$ref->code_reference} - {$ref->designation} - " .
                                                number_format($ref->prix_reference, 0, ',', ' ') . " FCFA";
                                        });
                                    })
                                    ->live(debounce: 1000)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state && $state !== 'manual') {
                                            // Cache aussi la récupération de la référence
                                            $reference = \Cache::remember("mercuriale_full_{$state}", now()->addMinutes(10), function () use ($state) {
                                                return \App\Models\ReferenceMercuriale::find($state);
                                            });

                                            if ($reference) {
                                                $set('designation', $reference->designation);
                                                $set('unite', $reference->unite);
                                                $set('prix_unitaire_ht', $reference->prix_reference);
                                                $set('reference_personnalisee', null);
                                            }
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
                                            ->default(19.25)
                                            ->suffix('%')
                                            ->default(fn(callable $get) => (float) ($get('../../tva_commune') ?? 0))
                                            ->disabled(fn(callable $get) => (bool) $get('../../exonere_tva'))
                                            ->dehydrated(true)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, callable $set, callable $get) => static::recalculerLigne($set, $get))
                                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                // Forcer le taux à 0 si exonéré lors du chargement
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
                                Forms\Components\Hidden::make('quantite_livree')->default(0),
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
        $quantite = (float) ($get('quantite') ?? 0);
        $prixUnitaireHT = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTVA = (float) ($get('taux_tva') ?? 19.25);
        $tauxIR = (float) ($get('taux_ir') ?? 0);

        // 1. Calcul du montant HT
        $montantHT = $quantite * $prixUnitaireHT;
        $set('montant_ht', round($montantHT, 2));

        // 2. Calcul du montant TVA
        $montantTVA = ($montantHT * $tauxTVA) / 100;
        $set('montant_tva', round($montantTVA, 2));

        // 3. Calcul du montant TTC
        $montantTTC = $montantHT + $montantTVA;
        $set('montant_ttc', round($montantTTC, 2));

        // 4. Calcul de l'IR
        $montantIR = 0;

        // ✅ Vérifier d'abord si IR est exonéré au niveau du BC
        $exonereIR = $get('../../exonere_ir') ?? false;

        if ($exonereIR) {
            // Si exonéré au niveau du BC, IR = 0
            $montantIR = 0;
            $set('taux_ir', 0);
        } elseif ($tauxIR > 0) {
            // IR manuel spécifié
            $montantIR = ($montantHT * $tauxIR) / 100;
        } else {
            // IR automatique basé sur le type d'engagement et le régime fiscal
            $typeEngagementId = $get('../../type_engagement_id');
            $fournisseurId = $get('../../fournisseur_id');

            if ($typeEngagementId && $fournisseurId && $montantHT > 0) {
                $typeEngagement = \App\Models\TypeEngagement::find($typeEngagementId);
                $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);

                if ($typeEngagement && $fournisseur && $fournisseur->regimeFiscal) {
                    $tauxIRAuto = $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                    $montantIR = ($montantHT * $tauxIRAuto) / 100;
                    $set('taux_ir', $tauxIRAuto);
                }
            }
        }

        $set('montant_ir', round($montantIR, 2));

        // 5. Calcul du net à payer
        $netAPayer = $montantHT - $montantIR;
        $set('net_a_payer', round($netAPayer, 2));

        // 6. Mettre à jour quantite_restante
        $quantiteLivree = (float) ($get('quantite_livree') ?? 0);
        $set('quantite_restante', max(0, $quantite - $quantiteLivree));

        // 7. Déclencher le recalcul des totaux du BC
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
        if (!$lignes) {
            return;
        }

        $totalHT = 0;
        $totalTVA = 0;
        $totalIR = 0;
        $totalTTC = 0;
        $totalNetAPayer = 0;

        foreach ($lignes as $ligne) {
            $totalHT += (float) ($ligne['montant_ht'] ?? 0);
            $totalTVA += (float) ($ligne['montant_tva'] ?? 0);
            $totalIR += (float) ($ligne['montant_ir'] ?? 0);
            $totalTTC += (float) ($ligne['montant_ttc'] ?? 0);
            $totalNetAPayer += (float) ($ligne['net_a_payer'] ?? 0);
        }

        // Mise à jour des totaux du BC
        $set('montant_ht', round($totalHT, 2));
        $set('montant_tva', round($totalTVA, 2));
        $set('montant_ir', round($totalIR, 2));
        $set('montant_ttc', round($totalTTC, 2));

        // Net à payer du BC = somme des nets à payer des lignes
        // OU si vous préférez : HT total - IR total
        $set('net_a_payer', round($totalNetAPayer, 2));
        // Alternative : $set('net_a_payer', round($totalHT - $totalIR, 2));
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
            // 1. Documents créés par moi (toujours visibles)
            $q->where('created_by', $user->id)

                // OU

                // 2. Documents sans transmission en cours (tout le monde peut voir)
                ->orWhereDoesntHave('transmissions', function ($transmission) {
                    $transmission->where('statut', 'en_attente');
                })

                // OU

                // 3. Documents dont je suis le destinataire actuel
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
                    ->label('N° BC')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                Tables\Columns\TextColumn::make('serviceDemandeur.nom')
                    ->label('Service')
                    ->searchable(),
                // ->toggleable(),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')
                    ->money('XAF')
                    ->sortable()
                    ->color('warning'),
                // ->toggleable(),

                Tables\Columns\TextColumn::make('   ')
                    ->label('Net à Percevoir')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn($record) => "HT: " . number_format($record->montant_ht, 0, ',', ' ') . " - IR: " . number_format($record->montant_ir, 0, ',', ' ')),
                // ->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning' => 'valide',
                        'primary' => 'engage',
                        'info' => 'en_cours',
                        'success' => fn($state) => in_array($state, ['livre_partiellement', 'livre']),
                        'danger' => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon' => 'Brouillon',
                        'valide' => 'Validé',
                        'engage' => 'Engagé',
                        'en_cours' => 'En cours',
                        'livre_partiellement' => 'Livré part.',
                        'livre' => 'Livré',
                        'annule' => 'Annulé',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('engage')
                    ->label('Engagé')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('typeEngagement.libelle')
                    ->label('Type')
                    ->badge()
                    ->color(fn($record) => match ($record->typeEngagement?->code) {
                        'BC' => 'success',
                        'LC' => 'warning',
                        'MARCHE' => 'primary',
                        'DECOMPTE_LC', 'DECOMPTE_MARCHE' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                // ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    // ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('engagement.reference_document')
                    ->label('N° Engagement')
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-banknotes')
                    ->placeholder('-')
                    ->visible(fn($record) => $record && $record->engage && $record->engagement)
                    ->description(
                        fn($record) =>
                        $record && $record->engagement && $record->date_engagement
                            ? 'Engagé le ' . $record->date_engagement->format('d/m/Y')
                            : null
                    ),

                // Modifier la colonne montant_ir pour montant_total_impots
                Tables\Columns\TextColumn::make('montant_total_impots')
                    ->label('Total Impôts')
                    ->getStateUsing(fn($record) => $record->calculerMontantTotalImpots())
                    ->money('XAF')
                    ->sortable()
                    ->color('warning')
                    ->description(function ($record) {
                        $details = [];
                        if ($record->montant_tva > 0) $details[] = "TVA: " . number_format($record->montant_tva, 0, ',', ' ');
                        if ($record->montant_ir > 0) $details[] = "IR: " . number_format($record->montant_ir, 0, ',', ' ');
                        if ($record->montant_tsr > 0) $details[] = "TSR: " . number_format($record->montant_tsr, 0, ',', ' ');
                        if ($record->montant_cnps > 0) $details[] = "CNPS: " . number_format($record->montant_cnps, 0, ',', ' ');

                        return implode(' | ', $details);
                    }),
                // ->toggleable(),

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
                    ->placeholder('-'),
                // ->toggleable(),

            ])
            ->filters([

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

                Tables\Filters\Filter::make('mes_bons')
                    ->label('📁 Tous mes bons')
                    ->query(function ($query) {
                        return $query->where('created_by', auth()->id());
                    })
                    ->toggle()
                    ->default(false)
                    ->indicateUsing(fn() => '📁 Tous les bons (créés par moi)'),

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
            ->actions(
                WorkflowActions::make(
                    avecEngagement: true,
                    pdfServiceClass: BonCommandePdfService::class,
                    pdfRouteName: 'bons-commande.pdf.preview',
                    avecModalEngagement: true
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
