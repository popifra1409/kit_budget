<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BonCommandeResource\Pages;
use App\Filament\Resources\BonCommandeResource\RelationManagers;
use App\Models\BonCommande;
use App\Models\Budget;
use App\Models\Fournisseur;
use App\Models\Service;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use App\Models\User;
use App\Filament\Actions\WorkflowActions;

class BonCommandeResource extends Resource
{
    protected static ?string $model = BonCommande::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Bons de Commande';

    protected static ?string $modelLabel = 'Bon de Commande';

    protected static ?string $pluralModelLabel = 'Bons de Commande';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

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
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state) {
                                    return;
                                }

                                $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($state);

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
                            }),

                        Forms\Components\Select::make('service_demandeur_id')
                            ->label('Service demandeur')
                            ->options(Service::where('actif', true)->pluck('nom', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Dates')
                    ->schema([
                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('date_livraison_prevue')
                            ->label('Date de livraison prévue')
                            ->required()
                            ->after('date_emission'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objet')
                    ->schema([
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet du bon de commande')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Fourniture de matériel informatique')
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
                            ->relationship('typeEngagement', 'libelle')
                            ->searchable()
                            ->preload()
                            ->live()
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
                            ->live()
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
                        Forms\Components\Select::make('nomenclature_commune_id')
                            ->label('Nomenclature budgétaire (commune à toutes les lignes)')
                            ->options(function (callable $get) {
                                $budgetId = $get('budget_id');
                                if (!$budgetId) {
                                    return ['Veuillez d\'abord sélectionner un budget'];
                                }

                                return \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
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
                            ->live()
                            ->helperText('Cette nomenclature sera automatiquement assignée à toutes les lignes ci-dessous')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('lignes')
                            ->relationship('lignes')
                            ->schema([
                                Forms\Components\Hidden::make('nomenclature_id')
                                    ->default(fn(callable $get) => $get('../../nomenclature_commune_id')),

                                Forms\Components\Placeholder::make('nomenclature_info')
                                    ->label('Nomenclature')
                                    ->content(function (callable $get) {
                                        $nomenclatureId = $get('../../nomenclature_commune_id');
                                        if (!$nomenclatureId) {
                                            return 'Sélectionnez d\'abord la nomenclature ci-dessus';
                                        }
                                        $nomenclature = \App\Models\NomenclatureBudgetaire::find($nomenclatureId);
                                        return $nomenclature ? "{$nomenclature->code} - {$nomenclature->libelle}" : '-';
                                    })
                                    ->columnSpan(2),

                                // ===== NOUVEAU : Choix Mercuriale ou Saisie Libre =====
                                Forms\Components\Select::make('reference_mercuriale_id')
                                    ->label('Référence Mercuriale')
                                    ->options(function (callable $get) {
                                        $exerciceId = $get('../../exercice_id');
                                        if (!$exerciceId) {
                                            return ['Veuillez d\'abord sélectionner un exercice'];
                                        }

                                        $references = \App\Models\ReferenceMercuriale::where('exercice_id', $exerciceId)
                                            ->where('actif', true)
                                            ->get()
                                            ->mapWithKeys(fn($ref) => [
                                                $ref->id => "{$ref->code_reference} - {$ref->designation} ({$ref->unite}) - " .
                                                    number_format($ref->prix_reference, 0, ',', ' ') . " FCFA"
                                            ]);

                                        return ['manual' => '➕ Saisie manuelle (sans mercuriale)'] + $references->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state && $state !== 'manual') {
                                            $reference = \App\Models\ReferenceMercuriale::find($state);
                                            if ($reference) {
                                                $set('designation', $reference->designation);
                                                $set('unite', $reference->unite);
                                                $set('prix_unitaire_ht', $reference->prix_reference);
                                            }
                                        } else {
                                            // Réinitialiser pour saisie manuelle
                                            $set('designation', '');
                                            $set('unite', 'pièce');
                                            $set('prix_unitaire_ht', 0);
                                        }
                                    })
                                    ->helperText('Choisissez une référence mercuriale ou "Saisie manuelle"')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('designation')
                                    ->label('Désignation')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ex: Ordinateur portable HP EliteBook')
                                    ->disabled(fn(callable $get) => $get('reference_mercuriale_id') && $get('reference_mercuriale_id') !== 'manual')
                                    ->dehydrated()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unite')
                                    ->label('Unité')
                                    ->maxLength(255)
                                    ->placeholder('pièce, kg, m, etc.')
                                    ->default('pièce')
                                    ->disabled(fn(callable $get) => $get('reference_mercuriale_id') && $get('reference_mercuriale_id') !== 'manual')
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('quantite')
                                    ->label('Quantité')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        self::recalculerLigne($set, $get);
                                    }),

                                Forms\Components\TextInput::make('prix_unitaire_ht')
                                    ->label('Prix Unitaire HT')
                                    ->required()
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        self::recalculerLigne($set, $get);
                                    }),

                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('Taux TVA (%)')
                                    ->numeric()
                                    ->default(19.25)
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live(debounce: 500),

                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('Taux IR (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        self::recalculerLigne($set, $get);
                                    })
                                    ->helperText('Pré-rempli selon régime fiscal, modifiable'),

                                Forms\Components\Placeholder::make('montant_preview')
                                    ->label('Montants estimés')
                                    ->content(function (callable $get) {
                                        $qte = (float) ($get('quantite') ?? 0);
                                        $pu = (float) ($get('prix_unitaire_ht') ?? 0);
                                        $tva = (float) ($get('taux_tva') ?? 19.25);
                                        $tauxIr = (float) ($get('taux_ir') ?? 0);

                                        $ht = $qte * $pu;
                                        $montantTva = $ht * ($tva / 100);
                                        $ttc = $ht + $montantTva;

                                        // Calculer IR
                                        $fournisseurId = $get('../../fournisseur_id');
                                        $ir = 0;

                                        if ($tauxIr > 0) {
                                            // Taux manuel fourni
                                            $ir = $ht * ($tauxIr / 100);
                                        } elseif ($fournisseurId) {
                                            // Utiliser le régime du fournisseur
                                            $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);
                                            if ($fournisseur && $fournisseur->regimeFiscal) {
                                                $ir = $fournisseur->calculerIR($ht);
                                            }
                                        }

                                        // Net à payer = HT - IR
                                        $net = $ht - $ir;

                                        return "HT: " . number_format($ht, 0, ',', ' ') . " FCFA\n" .
                                            "TVA (" . number_format($tva, 2) . "%): " . number_format($montantTva, 0, ',', ' ') . " FCFA\n" .
                                            "TTC: " . number_format($ttc, 0, ',', ' ') . " FCFA\n" .
                                            "IR: " . number_format($ir, 0, ',', ' ') . " FCFA\n" .
                                            "Net à payer: " . number_format($net, 0, ',', ' ') . " FCFA";
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(6)
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['designation'] ?? 'Nouvelle ligne'
                            )
                            ->addActionLabel('Ajouter une ligne')
                            ->columnSpanFull()
                            ->minItems(1)
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, callable $get): array {
                                $data['nomenclature_id'] = $get('nomenclature_commune_id');

                                // Nettoyer reference_mercuriale_id si c'est "manual"
                                if (isset($data['reference_mercuriale_id']) && $data['reference_mercuriale_id'] === 'manual') {
                                    unset($data['reference_mercuriale_id']);
                                }

                                return $data;
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, callable $get): array {
                                return $data;
                            }),
                    ]),
            ]);
    }

    /**
     * Recalculer une ligne (IR, montants, etc.)
     */
    protected static function recalculerLigne(callable $set, callable $get): void
    {
        $qte = (float) ($get('quantite') ?? 0);
        $pu = (float) ($get('prix_unitaire_ht') ?? 0);
        $ht = $qte * $pu;

        // Calculer l'IR automatiquement si pas de taux manuel
        $tauxIr = (float) ($get('taux_ir') ?? 0);

        if ($tauxIr == 0 && $ht > 0) {
            $fournisseurId = $get('../../fournisseur_id');
            if ($fournisseurId) {
                $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($fournisseurId);
                if ($fournisseur && $fournisseur->regimeFiscal) {
                    $tauxCalcule = $fournisseur->regimeFiscal->taux_ir_defaut;
                    $set('taux_ir', $tauxCalcule);
                }
            }
        }
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
                    ->label('N° BC')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                Tables\Columns\TextColumn::make('serviceDemandeur.nom')
                    ->label('Service')
                    ->searchable()
                    ->toggleable(),

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
                    ->color('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('net_a_percevoir')
                    ->label('Net à Percevoir')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn($record) => "HT: " . number_format($record->montant_ht, 0, ',', ' ') . " - IR: " . number_format($record->montant_ir, 0, ',', ' '))
                    ->toggleable(),

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
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

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
                    })
                    ->toggleable(),

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
                            ->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? null;

                        if (!$periode) {
                            return $query;
                        }

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
                            // 1. Documents créés par moi et en brouillon
                            $q->where(function ($subQ) use ($userId) {
                                $subQ->where('created_by', $userId)
                                    ->where('statut', 'brouillon');
                            })
                                // OU
                                // 2. Documents transmis à moi (en attente)
                                ->orWhereHas('transmissions', function ($transmission) use ($userId) {
                                    $transmission->where('destinataire_id', $userId)
                                        ->where('statut', 'en_attente');
                                });
                        });
                    })
                    ->toggle()
                    ->default(), // Activé par défaut

            ])
            ->actions(
                WorkflowActions::make(avecEngagement: true)
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
