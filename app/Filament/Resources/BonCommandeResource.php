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

class BonCommandeResource extends Resource
{
    protected static ?string $model = BonCommande::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Bons de Commande';

    protected static ?string $modelLabel = 'Bon de Commande';

    protected static ?string $pluralModelLabel = 'Bons de Commande';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions - Bons de commande avec validation
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
            'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    /**
     * Vérifier si l'utilisateur peut éditer ce bon de commande
     * Conditions : Rôle autorisé + Exercice modifiable
     */
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
        if (!$user->hasAnyRole(['operateur_budget', 'chef_service_budget'])) {
            return false;
        }

        // 4. Vérifier que l'exercice est modifiable
        return $record->estModifiable();
    }

    /**
     * Vérifier si l'utilisateur peut supprimer ce bon de commande
     * Conditions : Super admin uniquement + Exercice modifiable
     */

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
     * Action spéciale : Valider un bon de commande
     */
    public static function canValider($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier'
        ]) : false;
    }

    /**
     * Action spéciale : Annuler un bon de commande
     */
    public static function canAnnuler($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget',
            'sous_directeur_budget',
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
                            ->preload(),

                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur')
                            ->options(
                                Fournisseur::where('actif', true)
                                    ->where('blackliste', false)
                                    ->get()
                                    ->mapWithKeys(fn($f) => [$f->id => "{$f->code} - {$f->raison_sociale}"])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Code')
                                            ->required()
                                            ->unique('fournisseurs', 'code')
                                            ->maxLength(50)
                                            ->placeholder('Ex: FRS-002'),

                                        Forms\Components\TextInput::make('raison_sociale')
                                            ->label('Raison sociale')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('nif')
                                            ->label('NIF')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('telephone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(255),

                                        Forms\Components\Select::make('type')
                                            ->label('Type')
                                            ->options([
                                                'biens' => 'Biens',
                                                'services' => 'Services',
                                                'travaux' => 'Travaux',
                                                'mixte' => 'Mixte',
                                            ])
                                            ->default('mixte')
                                            ->required(),
                                    ]),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['actif'] = true;
                                $data['blackliste'] = false;
                                return Fournisseur::create($data)->id;
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

                                Forms\Components\TextInput::make('designation')
                                    ->label('Désignation')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ex: Ordinateur portable HP EliteBook')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unite')
                                    ->label('Unité')
                                    ->maxLength(255)
                                    ->placeholder('pièce, kg, m, etc.')
                                    ->default('pièce'),

                                Forms\Components\TextInput::make('quantite')
                                    ->label('Quantité')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->live(onBlur: true),

                                Forms\Components\TextInput::make('prix_unitaire_ht')
                                    ->label('Prix Unitaire HT')
                                    ->required()
                                    ->numeric()
                                    ->prefix('FCFA')
                                    ->default(0)
                                    ->live(onBlur: true),

                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('Taux TVA (%)')
                                    ->numeric()
                                    ->default(19.25)
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live(onBlur: true),

                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('Taux IR (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live(onBlur: true)
                                    ->helperText('Laissez vide pour calcul automatique selon barème'),

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
                                        if ($tauxIr > 0) {
                                            $ir = $ht * ($tauxIr / 100);
                                        } else {
                                            // Barème automatique
                                            if ($ht < 500000) {
                                                $ir = $ht * 0.055;
                                            } elseif ($ht < 3000000) {
                                                $ir = $ht * 0.11;
                                            } else {
                                                $ir = $ht * 0.15;
                                            }
                                        }

                                        $net = $ttc - $ir;

                                        return "TTC: " . number_format($ttc, 0, ',', ' ') . " FCFA\n" .
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
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, callable $get): array {
                                // Pour l'édition, on pré-remplit avec la nomenclature commune
                                return $data;
                            }),
                    ]),
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
                            ->title('BC validé')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('engager')
                    ->label('Engager')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn($record) => $record->statut === 'valide' && !$record->engage)
                    ->requiresConfirmation()
                    ->modalHeading('Engager le budget')
                    ->modalDescription(
                        fn($record) =>
                        "Engager le budget pour ce BC de " . number_format($record->montant_ttc, 0, ',', ' ') . " FCFA ? " .
                            "Cette action consommera le budget des lignes budgétaires concernées."
                    )
                    ->action(function ($record) {
                        try {
                            $record->engagerBudget();
                            Notification::make()
                                ->title('Budget engagé avec succès')
                                ->success()
                                ->body('Le budget a été consommé sur les lignes budgétaires.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur lors de l\'engagement')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => !in_array($record->statut, ['annule', 'livre']))
                    ->requiresConfirmation()
                    ->modalHeading('Annuler le BC')
                    ->modalDescription('Êtes-vous sûr de vouloir annuler ce BC ? Si le budget est engagé, il sera désengagé automatiquement.')
                    ->action(function ($record) {
                        $record->annuler();
                        Notification::make()
                            ->title('BC annulé')
                            ->warning()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('telecharger_bon_commande')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn($record) => route('pdf.telecharger', [
                        'etat' => 'bon_commande',
                        'id' => $record->id
                    ])),

                Action::make('afficher_bon_commande')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('pdf.afficher', [
                        'etat' => 'bon_commande',
                        'id' => $record->id
                    ]))
                    ->openUrlInNewTab(),
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
