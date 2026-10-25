<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\RegieAvanceResource\Pages;
use App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;
use App\Models\RegieAvance;
use App\Models\Budget;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Exercice;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RegieAvanceResource extends Resource
{
    protected static ?string $model           = RegieAvance::class;
    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Régies d\'Avance';
    protected static ?string $modelLabel      = 'Régie d\'Avance';
    protected static ?string $pluralModelLabel = 'Régies d\'Avance';
    protected static ?string $navigationGroup = 'Régies & menus de dépense';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_regie_avance') ?? false;
    }

    /**
     * Un responsable de régie n'accède qu'à ses propres régies ; la supervision
     * (permission superviser_toutes_les_regies) accède à toutes.
     */
    public static function accessible($record): bool
    {
        return $record?->estGerePar(auth()->user()) ?? false;
    }

    public static function canView($record): bool
    {
        return (auth()->user()?->can('view_regie_avance') ?? false) && static::accessible($record);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_regie_avance') ?? false;
    }

    public static function canEdit($record): bool
    {
        return (auth()->user()?->can('update_regie_avance') ?? false)
            && static::accessible($record)
            && in_array($record->statut, ['actif']);
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('delete_regie_avance') ?? false)
            && static::accessible($record)
            && $record->statut === 'actif'
            // Un BCR engagé a déjà consommé l'encaisse : la régie n'est plus vide.
            && (float) $record->montant_engage == 0.0
            && $record->montant_depense == 0;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'libelle', 'objet', 'statut'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'libelle' => $record->libelle,
            'objet' => $record->objet,
            'statut' => $record->statut,
            'type' => $record->type,
        ];
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Section 1 : Identification ────────────────────
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        // ✅ Live + afterStateUpdated → sync objet si vide
                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé / Désignation')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Régie d\'avance principale DAAF')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (empty($get('objet'))) {
                                    $set('objet', $state);
                                }
                            })
                            ->columnSpan(2),
                    ]),

                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->options(fn() => Exercice::orderByDesc('annee')
                                ->pluck('annee', 'id'))
                            ->default(fn() => Exercice::getActif()?->id)
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Select::make('responsable_id')
                            ->label('Responsable')
                            ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Utilisateur gestionnaire de cette régie'),
                    ]),
                ]),

            // ── Section 2 : Décision Administrative source ────
            Forms\Components\Section::make('Dotation et décision administrative source')
                ->description(
                    '💡 Après création de la régie, associez la DA source '
                        . 'depuis l\'onglet "Décision source" de la fiche.'
                )
                ->schema([

                    // ── Ligne 1 : Encaisse + Net à décaisser + Restant ──
                    Forms\Components\Grid::make(3)->schema([

                        // ✅ Encaisse annuelle — nouveau champ
                        Forms\Components\TextInput::make('encaisse_annuelle')
                            ->label('Encaisse annuelle (FCFA)')
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($state ?? 0);
                                $net      = (float) ($get('montant_alloue') ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Montant total alloué annuellement à la régie'),


                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('sync_depuis_da')
                                ->label('↺ Sync montant net depuis DA associée')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->size('sm')
                                ->visible(
                                    fn(Get $get, $record) =>
                                    $record?->decision_administrative_id !== null
                                )
                                ->action(function (Set $set, $record) {
                                    $da = \App\Models\DecisionAdministrative::find(
                                        $record?->decision_administrative_id
                                    );
                                    if (!$da) return;

                                    // ✅ Uniquement montant_alloue
                                    $set('montant_alloue', $da->montant_net);
                                    // ❌ encaisse_annuelle non touchée

                                    \Filament\Notifications\Notification::make()
                                        ->title('✅ Montant net syncé depuis ' . $da->numero)
                                        ->body(number_format($da->montant_net, 0, ',', ' ') . ' FCFA')
                                        ->success()->send();
                                }),
                        ])->columnSpanFull(),

                        // ✅ Montant net à décaisser (anciennement "Montant alloué")
                        Forms\Components\TextInput::make('montant_alloue')
                            ->label('Montant net à décaisser (FCFA)')
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($get('encaisse_annuelle') ?? 0);
                                $net      = (float) ($state ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Sera mis à jour après association de la DA source.'),

                        // ✅ Montant encaisse restant — calculé, lecture seule
                        Forms\Components\Placeholder::make('montant_encaisse_restant_affiche')
                            ->label('Montant encaisse restant (FCFA)')
                            ->content(function (Get $get, $record) {
                                $encaisse = (float) ($get('encaisse_annuelle')
                                    ?? $record?->encaisse_annuelle ?? 0);
                                $net = (float) ($get('montant_alloue')
                                    ?? $record?->montant_alloue ?? 0);
                                $restant = max(0, $encaisse - $net);
                                $style = $restant <= 0
                                    ? 'color:red; font-weight:bold;'
                                    : 'color:green; font-weight:bold;';
                                return new \Illuminate\Support\HtmlString(
                                    "<span style='{$style}'>"
                                        . number_format($restant, 0, ',', ' ')
                                        . ' FCFA</span>'
                                );
                            }),
                    ]),

                    // ── Ligne 2 : Date + Objet ──────────────────────────
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\DatePicker::make('date_creation')
                            ->label('Date de création')
                            ->default(now())
                            ->required(),

                        // ✅ Objet — auto-rempli depuis libelle, modifiable
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet de la régie')
                            ->rows(2)
                            ->placeholder('Rempli automatiquement depuis le libellé')
                            ->helperText('Récupéré depuis le libellé — modifiable')
                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                if (empty($state) && !empty($get('libelle'))) {
                                    $set('objet', $get('libelle'));
                                }
                            }),
                    ]),

                    // ✅ Bouton sync objet ← libellé
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('sync_objet')
                            ->label('↺ Synchroniser objet depuis libellé')
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->size('sm')
                            ->action(function (Set $set, Get $get) {
                                $set('objet', $get('libelle'));
                            }),
                    ])->columnSpanFull(),

                    Forms\Components\Placeholder::make('info_source')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-blue-50 dark:bg-blue-900/30 '
                                . 'text-blue-800 dark:text-blue-200 '
                                . 'border border-blue-200 dark:border-blue-700">'
                                . '<strong>📋 Étapes après création :</strong>'
                                . '<ol class="mt-2 ml-4 list-decimal leading-loose">'
                                . '<li>Cliquez <strong>Créer</strong> pour sauvegarder la régie</li>'
                                . '<li>Dans la fiche → onglet <strong>"Décision source"</strong> '
                                . '→ <strong>"Associer une DA"</strong></li>'
                                . '<li>Sélectionnez la DA engagée — montant et ligne budgétaire '
                                . 'seront récupérés automatiquement</li>'
                                . '</ol>'
                                . '</div>'
                        ))
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Pièces de l'apurement ─────────────
            Forms\Components\Section::make('Pièces de l\'apurement')
                ->description(
                    'Ces références composent le texte qui précède le tableau des dépenses '
                        . 'de l\'état du compte d\'emploi. Une ligne dont le numéro manque '
                        . 'n\'est pas imprimée.'
                )
                ->schema([
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('resolution_numero')
                            ->label('Résolution autorisant le renouvellement — n°')
                            ->maxLength(120)
                            ->placeholder('Ex: 026/0003/R/CHUY/CA/DG')
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('resolution_date')
                            ->label('du'),
                    ]),

                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('decision_ouverture_numero')
                            ->label('Décision portant ouverture de la régie — n°')
                            ->maxLength(120)
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('decision_ouverture_date')
                            ->label('du'),
                    ]),

                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('decision_designation_numero')
                            ->label('Décision de désignation des responsables — n°')
                            ->maxLength(120)
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('decision_designation_date')
                            ->label('du'),
                    ]),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed(),

            // ── Section 4 : Observations ──────────────────────
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

    // =========================================================
    // TABLEAU
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° RAV')
                    ->searchable()->sortable()
                    ->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Désignation')
                    ->searchable()->limit(35)
                    ->tooltip(fn($record) => $record->libelle),

                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->searchable()->sortable(),

                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->badge()->color('info'),

                // ✅ Encaisse annuelle
                Tables\Columns\TextColumn::make('encaisse_annuelle')
                    ->label('Encaisse annuelle')
                    ->money('XAF')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_alloue')
                    ->label('Net à décaisser')
                    ->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('montant_decaisse')
                    ->label('Décaissé')
                    ->money('XAF')->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Engagé')
                    ->money('XAF')->sortable()
                    ->color('danger')
                    ->weight('bold')
                    ->tooltip(function ($record) {
                        $texte = "BCR engagés + achats directs validés : la régie est "
                            . "consommée dès l'engagement, avant livraison et paiement.";

                        $deficit = $record->montantPayeNonEngage();
                        if ($deficit > 0.01) {
                            $texte .= ' — dont ' . number_format($deficit, 0, ',', ' ')
                                . ' FCFA payés sans engagement ni encaisse, à régulariser '
                                . "par une nouvelle DA (budget:audit-regie les liste).";
                        }

                        return $texte;
                    }),

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Livré / payé')
                    ->money('XAF')->sortable()
                    ->color('warning')
                    ->tooltip('Part déjà livrée ou payée — déjà comprise dans « Engagé »'),

                // ✅ NOUVEAU — IR collecté sur l'ensemble des décaissements
                Tables\Columns\TextColumn::make('montant_ir_collecte')
                    ->label('IR collecté')
                    ->getStateUsing(
                        fn($record) => (float) (
                            $record->ir_collecte_calcule
                            ?? $record->decaissements()->sum('montant_ir_collecte')
                        )
                    )
                    ->money('XAF')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_disponible')
                    ->label('Disponible')
                    ->money('XAF')->sortable()
                    ->weight('bold')
                    ->color(fn($record) => (float) $record->montant_disponible < 0
                        ? 'danger' : 'success')
                    ->tooltip('= Décaissé − Engagé'),

                Tables\Columns\TextColumn::make('taux_consommation')
                    ->label('Consommation')
                    ->formatStateUsing(
                        fn($record) => number_format($record->taux_consommation, 1) . '%'
                    )
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->taux_consommation >= 90 => 'danger',
                        $record->taux_consommation >= 70 => 'warning',
                        default                          => 'success',
                    }),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'success' => 'actif',
                        'warning' => 'suspendu',
                        'danger'  => 'cloture',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'actif'    => 'Actif',
                        'suspendu' => 'Suspendu',
                        'cloture'  => 'Clôturé',
                        default    => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'actif'    => 'Actif',
                        'suspendu' => 'Suspendu',
                        'cloture'  => 'Clôturé',
                    ]),

                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('responsable_id')
                    ->label('Responsable')
                    ->relationship('responsable', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('recalculer')
                        ->label('Recalculer les montants')
                        ->icon('heroicon-o-calculator')
                        ->color('gray')
                        ->tooltip('Recalcule montant_depense et montant_disponible depuis les décaissements')
                        ->action(function ($record) {
                            $record->recalculerMontants();
                            \Filament\Notifications\Notification::make()
                                ->title('✅ Montants recalculés')
                                ->body(
                                    'Dépensé : ' . number_format($record->fresh()->montant_depense, 0, ',', ' ') . ' FCFA'
                                        . ' | Disponible : ' . number_format($record->fresh()->montant_disponible, 0, ',', ' ') . ' FCFA'
                                )
                                ->success()
                                ->send();
                        }),

                    // ── Suspendre ────────────────────────────────
                    Tables\Actions\Action::make('suspendre')
                        ->label('Suspendre')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'actif'
                                && auth()->user()?->can('suspendre_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Suspendre la régie')
                        ->modalDescription(
                            'La régie sera suspendue — aucune dépense ne pourra être enregistrée.'
                        )
                        ->action(function ($record) {
                            $record->update(['statut' => 'suspendu']);
                            Notification::make()->title('Régie suspendue')->warning()->send();
                        }),

                    // ── Réactiver ────────────────────────────────
                    Tables\Actions\Action::make('reactiver')
                        ->label('Réactiver')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'suspendu'
                                && auth()->user()?->can('suspendre_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['statut' => 'actif']);
                            Notification::make()->title('Régie réactivée')->success()->send();
                        }),

                    // ── Clôturer ─────────────────────────────────
                    Tables\Actions\Action::make('cloturer')
                        ->label('Clôturer')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(
                            fn($record) =>
                            in_array($record->statut, ['actif', 'suspendu'])
                                && auth()->user()?->can('cloturer_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Clôturer la régie')
                        ->modalDescription(
                            'La régie sera définitivement clôturée. Cette action est irréversible.'
                        )
                        ->form([
                            Forms\Components\DatePicker::make('date_cloture')
                                ->label('Date de clôture')
                                ->default(now())
                                ->required(),
                            Forms\Components\Textarea::make('observations')
                                ->label('Observations de clôture')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'statut'       => 'cloture',
                                'date_cloture' => $data['date_cloture'],
                                'observations' => ($record->observations ?? '')
                                    . "\n\n--- CLÔTURÉE LE " . now()->format('d/m/Y') . " ---\n"
                                    . ($data['observations'] ?? ''),
                            ]);
                            Notification::make()->title('✅ Régie clôturée')->success()->send();
                        }),

                    // ✅ "Réapprovisionner" retiré — utilisait l'ancienne
                    //    RegieAvance::reapprovisionner() qui incrémentait les
                    //    totaux SANS créer l'association MenuDepenseDecision
                    //    ni la ligne mini-budget correspondante. Désormais,
                    //    le seul chemin correct est l'onglet "Décision source
                    //    (DA engagée)" sur la fiche de la régie.
                    // ── État Compte d'Emploi ───────────────────────────────────────
                    Tables\Actions\Action::make('etat_compte_emploi')
                        ->label('📄 Compte d\'Emploi')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->modalHeading(fn($record) => 'Compte d\'Emploi — ' . $record->numero)
                        ->modalWidth('2xl')
                        ->form([
                            Forms\Components\Select::make('decaissements_ids')
                                ->label('Encaisses apurées')
                                ->multiple()
                                ->options(fn($record) => $record->decaissements()
                                    ->orderBy('numero')
                                    ->get()
                                    ->mapWithKeys(fn($d) => [
                                        $d->id => trim("{$d->libelle_tranche} — {$d->numero} ("
                                            . number_format((float) $d->montant_accorde, 0, ',', ' ') . ' FCFA)'),
                                    ]))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, $record) {
                                    $decaissements = \App\Models\DecaissementRegie::whereIn('id', $state ?? [])
                                        ->where('regie_avance_id', $record->id)
                                        ->orderBy('numero')
                                        ->get();

                                    if ($decaissements->isEmpty()) {
                                        return;
                                    }

                                    $set('numero_tranche', \App\Models\RegieAvance::enteteTranches($decaissements));

                                    $premier = $decaissements
                                        ->sortBy(fn($d) => $d->date_decaissement ?? $d->date_demande ?? $record->date_creation)
                                        ->first();
                                    $dateDebut = $premier->date_decaissement ?? $premier->date_demande ?? $record->date_creation;
                                    $set('date_debut', \Carbon\Carbon::parse($dateDebut)->format('Y-m-d'));

                                    $set('texte_apurement', $record->lignesApurementPour($decaissements)->implode("\n"));
                                })
                                ->helperText('Plusieurs encaisses peuvent être apurés dans un seul document. Sont inclus les bons de commande de la régie ET les achats directs imputés à ces encaisses, chacun à son montant réellement imputé (un bon réparti sur deux encaisses n\'est compté qu\'une fois).')
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('date_debut')
                                    ->label('Période — Début')
                                    ->default(fn($record) => $record->date_creation)
                                    ->required(),
                                Forms\Components\DatePicker::make('date_fin')
                                    ->label('Période — Fin')
                                    ->default(now())
                                    ->required(),
                            ]),

                            Forms\Components\CheckboxList::make('statuts')
                                ->label('Statuts à inclure')
                                ->options([
                                    'paye'   => 'Payé',
                                    'livre'  => 'Livré',
                                    'valide' => 'Validé',
                                ])
                                ->default(['paye', 'livre', 'valide'])
                                ->columns(2)
                                ->required(),

                            Forms\Components\TextInput::make('numero_tranche')
                                ->label('Désignation des encaisses')
                                ->placeholder('Ex: 1ERE ENCAISSE 2026 ET LA 2EME ENCAISSE')
                                ->helperText('Complète le titre « APUREMENT DE LA … » — pré-remplie depuis les tranches choisies.'),

                            Forms\Components\Select::make('ordre_impression')
                                ->label('Ordre d\'impression')
                                ->options([
                                    'montant' => 'Montants payés décroissants — blocs « Bons de commande » puis « Achats directs », avec un sous-total par bloc',
                                    'date'    => 'Chronologique — les dates se suivent, un seul tableau sans blocs',
                                ])
                                ->default('montant')
                                ->required()
                                ->helperText('Le tri ne change ni les pièces ni les montants : il ne déplace que les lignes du tableau.'),

                            Forms\Components\Textarea::make('texte_apurement')
                                ->label('Pièces justificatives (une ligne par numéro)')
                                ->rows(12)
                                ->placeholder('Sélectionnez les encaisses : les pièces saisies sur la fiche de la régie et sur chaque encaisse apparaissent ici.')
                                ->helperText('Générées depuis la fiche de la régie (résolution, ouverture, désignation) et les « Pièces » de chaque encaisse. Modifiable avant impression ; les lignes manquantes ne sont pas imprimées.')
                                ->columnSpanFull(),
                        ])
                        ->action(function ($record, array $data) {
                            // ✅ Récupération de l'EtatConfig
                            $etatConfig = \App\Models\EtatConfig::where('code', 'etat_compte_emploi_regie')
                                ->where('est_defaut', true)
                                ->first();

                            $decaissements = \App\Models\DecaissementRegie::whereIn('id', $data['decaissements_ids'] ?? [])
                                ->where('regie_avance_id', $record->id)
                                ->orderBy('numero')
                                ->get();
                            $statutsFiltre = $data['statuts'] ?? ['paye', 'livre', 'valide'];
                            $ordre = $data['ordre_impression'] ?? 'montant';

                            // ✅ Une ligne par BCR : imputations de ce BCR sur les tranches
                            //    choisies additionnées — un bon réparti sur deux encaisses
                            //    n'est donc pas compté deux fois.
                            $depenses = static::ordonnerDepensesPourCompteEmploi(
                                static::depensesDesDecaissements($decaissements, $statutsFiltre, $data['date_debut'], $data['date_fin']),
                                $ordre
                            );

                            $lignesApurement = collect(explode("\n", $data['texte_apurement'] ?? ''))
                                ->map(fn($l) => trim($l))
                                ->filter()
                                ->values();

                            $totaux = [
                                'ttc' => $depenses->sum('montant_ttc'),
                                'tva' => $depenses->sum('montant_tva'),
                                'ir'  => $depenses->sum('montant_ir'),
                                'net' => $depenses->sum('net_a_payer'),
                            ];

                            $montantRecu = (float) $decaissements->sum('montant_accorde');

                            // ✅ $donnees structuré comme tous les autres documents
                            $donnees = [
                                '_raw'            => $record,
                                '_etat_config'    => $etatConfig,
                                'depenses'        => $depenses,
                                'lignesApurement' => $lignesApurement,
                                'numeroTranche'   => $data['numero_tranche'] ?: \App\Models\RegieAvance::enteteTranches($decaissements),
                                'dateDebut'       => \Carbon\Carbon::parse($data['date_debut']),
                                'dateFin'         => \Carbon\Carbon::parse($data['date_fin']),
                                'totaux'          => $totaux,
                                'ordre'           => $ordre,
                                'montantRecu'     => $montantRecu,
                                'soldeReverse'    => $montantRecu - (float) $totaux['ttc'],
                            ];

                            $template = $etatConfig?->template ?? 'pdf.templates.etat-compte-emploi-regie';

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, ['donnees' => $donnees])
                                ->setPaper('a4', 'landscape');

                            $nomFichier = 'Compte-Emploi-' . $record->numero . '-' . now()->format('Ymd') . '.pdf';

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                $nomFichier
                            );
                        }),

                    // ── État des Retenues Fiscales IR ───────────────────────────────
                    Tables\Actions\Action::make('etat_retenues_ir')
                        ->label('📄 Retenues IR')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('warning')
                        ->modalHeading(fn($record) => 'État Retenues Fiscales — ' . $record->numero)
                        ->modalWidth('lg')
                        ->form([
                            Forms\Components\Select::make('decaissement_id')
                                ->label('Décaissement / Tranche')
                                ->options(fn($record) => $record->decaissements()
                                    ->orderBy('numero')
                                    ->get()
                                    ->mapWithKeys(fn($d) => [$d->id => "{$d->numero} — {$d->libelle_tranche}"]))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, $record) {
                                    $d = \App\Models\DecaissementRegie::where('id', $state)
                                        ->where('regie_avance_id', $record->id)
                                        ->first();
                                    if ($d) {
                                        $set('numero_tranche', $d->libelle_tranche);
                                        if ($d->date_decaissement) {
                                            $set('date_debut', $d->date_decaissement->format('Y-m-d'));
                                        }
                                    }
                                })
                                ->helperText('Seules les retenues réellement imputées à ce décaissement seront incluses.')
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('date_debut')
                                    ->label('Période — Début')
                                    ->default(fn($record) => $record->date_creation)
                                    ->required(),
                                Forms\Components\DatePicker::make('date_fin')
                                    ->label('Période — Fin')
                                    ->default(now())
                                    ->required(),
                            ]),

                            Forms\Components\CheckboxList::make('statuts')
                                ->label('Statuts à inclure')
                                ->options([
                                    'paye'   => 'Payé',
                                    'livre'  => 'Livré',
                                    'valide' => 'Validé',
                                ])
                                ->default(['paye', 'livre', 'valide'])
                                ->columns(2)
                                ->required(),

                            Forms\Components\TextInput::make('numero_tranche')
                                ->label('N° de tranche / désignation')
                                ->placeholder('Ex: QUATRIÈME TRANCHE DE L\'ENCAISSE'),
                        ])
                        ->action(function ($record, array $data) {
                            // ✅ Récupération de l'EtatConfig
                            $etatConfig = \App\Models\EtatConfig::where('code', 'etat_retenues_ir_regie')
                                ->where('est_defaut', true)
                                ->first();

                            $decaissement = \App\Models\DecaissementRegie::with('provisions')
                                ->where('id', $data['decaissement_id'])
                                ->where('regie_avance_id', $record->id)
                                ->first();
                            $provisionIds = $decaissement?->provisions->pluck('id') ?? collect();
                            $statutsFiltre = $data['statuts'] ?? ['paye', 'livre', 'valide'];

                            // ✅ Une ligne par BCR : imputations de ce BCR sur CE décaissement additionnées ;
                            //    montants exacts si le BCR y est entièrement imputé, prorata sinon.
                            $depenses = static::depensesDuDecaissement($decaissement, $statutsFiltre, $data['date_debut'], $data['date_fin'])
                                ->filter(fn($d) => $d->montant_ir > 0)
                                ->values();

                            $totaux = [
                                'ttc' => $depenses->sum('montant_ttc'),
                                'tva' => $depenses->sum('montant_tva'),
                                'ir'  => $depenses->sum('montant_ir'),
                                'net' => $depenses->sum('net_a_payer'),
                            ];

                            // ✅ $donnees structuré comme tous les autres documents
                            $donnees = [
                                '_raw'          => $record,
                                '_etat_config'  => $etatConfig,
                                'depenses'      => $depenses,
                                'numeroTranche' => $data['numero_tranche'] ?? '',
                                'dateDebut'     => \Carbon\Carbon::parse($data['date_debut']),
                                'dateFin'       => \Carbon\Carbon::parse($data['date_fin']),
                                'totaux'        => $totaux,
                            ];

                            $template = $etatConfig?->template ?? 'pdf.templates.etat-retenues-ir-regie';

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, ['donnees' => $donnees])
                                ->setPaper('a4', 'landscape');

                            $nomFichier = 'Retenues-IR-' . $record->numero . '-' . now()->format('Ymd') . '.pdf';

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                $nomFichier
                            );
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DecisionSourceRavRelationManager::class,
            RelationManagers\LignesRegieRelationManager::class,
            RelationManagers\DecaissementsRelationManager::class,
            RelationManagers\DepensesRelationManager::class,
        ];
    }

    /**
     * ✅ Dépenses d'un décaissement (compte d'emploi, retenues IR) : UNE ligne par
     * pièce — bon de commande de régie comme achat direct.
     */
    public static function depensesDuDecaissement(?\App\Models\DecaissementRegie $decaissement, array $statuts, $debut, $fin): \Illuminate\Support\Collection
    {
        return static::depensesDesDecaissements(
            $decaissement ? collect([$decaissement]) : collect(),
            $statuts,
            $debut,
            $fin
        );
    }

    /**
     * ✅ Dépenses de plusieurs encaisses apurés ensemble : UNE ligne par pièce.
     *
     * Une régie paie des bons de commande ET des achats directs ; les deux
     * familles alimentent le compte d'emploi.
     *
     * BCR :
     *  - imputations du BCR sur les provisions des tranches choisies additionnées,
     *    donc un bon réparti sur deux encaisses n'apparaît qu'une fois au total réel ;
     *  - BCR entièrement imputé sur ces tranches : montants exacts du BCR ;
     *  - BCR encore réparti ailleurs : TTC = montant réellement imputé ici,
     *    TVA / IR / net au prorata (mention « (part.) »).
     * Achat direct : rattaché à l'encaisse par la provision qu'il a débitée, au
     * montant près (pas de répartition possible sur deux encaisses).
     *
     * La colonne « N° FACTURE » porte le numéro de facture quand il existe,
     * toujours suivi de la pièce d'origine entre parenthèses.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\DecaissementRegie> $decaissements
     */
    public static function depensesDesDecaissements(\Illuminate\Support\Collection $decaissements, array $statuts, $debut, $fin): \Illuminate\Support\Collection
    {
        if ($decaissements->isEmpty()) {
            return collect();
        }

        $provisionIds = \App\Models\ProvisionLigneRegie::whereIn('decaissement_regie_id', $decaissements->pluck('id'))
            ->pluck('id');
        $debut = \Carbon\Carbon::parse($debut)->startOfDay();
        $fin = \Carbon\Carbon::parse($fin)->endOfDay();

        $bonsDeCommande = \App\Models\ProvisionConsommation::whereIn('provision_ligne_regie_id', $provisionIds)
            ->with('bonCommandeRegie.fournisseur')
            ->get()
            ->groupBy('bon_commande_regie_id')
            ->map(function ($consommations) {
                $bcr = $consommations->first()->bonCommandeRegie;
                if (!$bcr) {
                    return null;
                }

                $ttc = (float) $bcr->montant_ttc;
                $impute = (float) $consommations->sum('montant');
                $complet = $ttc <= 0 || $impute >= $ttc - 0.5;
                $ratio = $ttc > 0 ? min(1, $impute / $ttc) : 0;
                $part = fn($v) => $complet ? (float) $v : round((float) $v * $ratio, 0);

                // Le n° de facture est parfois ressaisi à l'identique du bon :
                // une seule fois dans la cellule, sinon la référence est doublée.
                $facture = $bcr->numero_facture_definitive ?: '⚠️ facture non renseignée';

                return (object) [
                    'numero'        => $facture
                            . ($facture === $bcr->numero ? '' : ' (' . $bcr->numero . ')')
                            . ($complet ? '' : ' (part.)'),
                    'bcr'           => $bcr->numero,
                    'nature'        => 'bon_commande',
                    'statut'        => $bcr->statut,
                    'date_emission' => $bcr->date_emission,
                    'fournisseur'   => $bcr->fournisseur,
                    'fournisseur_nom' => $bcr->fournisseur?->raison_sociale,
                    'montant_ttc'   => $complet ? $ttc : round($impute, 0),
                    'montant_tva'   => $part($bcr->montant_tva),
                    'montant_ir'    => $part($bcr->montant_ir),
                    'net_a_payer'   => $part($bcr->net_a_payer),
                ];
            })
            ->filter()
            ->values();

        // Achats directs : la dépense a débité une provision, qui porte l'encaisse.
        // Comme pour un BCR, la colonne N° FACTURE porte le n° de facture du
        // fournisseur, le n° de l'achat (DR…) rappelé entre parenthèses.
        $achatsDirects = \App\Models\DepenseRegie::whereIn('provision_ligne_regie_id', $provisionIds)
            ->where('type_depense', 'achat_direct')
            ->with('fournisseur')
            ->get()
            ->map(function ($d) {
                $facture = $d->numero_facture ?: '⚠️ facture non renseignée';
                return (object) [
                    'numero'        => $facture
                            . ($facture === $d->numero ? '' : ' (' . $d->numero . ')'),
                    'bcr'           => $d->numero,
                    'nature'        => 'achat_direct',
                    'statut'        => $d->statut,
                    'date_emission' => $d->date_depense,
                    'fournisseur'   => $d->fournisseur,
                    'fournisseur_nom' => $d->fournisseur?->raison_sociale ?: $d->fournisseur_libre,
                    'montant_ttc'   => (float) $d->montant_ttc,
                    'montant_tva'   => (float) $d->montant_tva,
                    'montant_ir'    => (float) $d->montant_ir,
                    'net_a_payer'   => (float) $d->net_a_payer,
                ];
            });

        return $bonsDeCommande
            ->concat($achatsDirects)
            ->filter(fn($d) => in_array($d->statut, $statuts, true))
            ->filter(fn($d) => !$d->date_emission || \Carbon\Carbon::parse($d->date_emission)->between($debut, $fin))
            ->sortBy('date_emission')
            ->values();
    }

    /**
     * ✅ Ordre d'impression du compte d'emploi. Le tri ne change aucun montant,
     * il ne déplace que les lignes : « montant » regroupe les pièces par nature
     * (les bons de commande d'abord) puis les range du net à payer le plus élevé
     * au plus faible ; « date » laisse l'ordre chronologique du tableau simple.
     *
     * @param \Illuminate\Support\Collection<int, object> $depenses
     */
    public static function ordonnerDepensesPourCompteEmploi(
        \Illuminate\Support\Collection $depenses,
        string $ordre = 'montant'
    ): \Illuminate\Support\Collection {
        if ($ordre !== 'montant') {
            return $depenses->sortBy('date_emission')->values();
        }

        $rangBloc = array_flip(['bon_commande', 'achat_direct']);

        // Les deux tris sont stables (PHP >= 8) : décroître les montants d'abord
        // garde cet ordre à l'intérieur de chaque bloc.
        return $depenses
            ->sortByDesc('net_a_payer')
            ->sortBy(fn($d) => $rangBloc[$d->nature] ?? count($rangBloc))
            ->values();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRegiesAvances::route('/'),
            'create' => Pages\CreateRegieAvance::route('/create'),
            'edit'   => Pages\EditRegieAvance::route('/{record}/edit'),
            'view'   => Pages\ViewRegieAvance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'rav')
            ->with(['exercice', 'responsable', 'budget'])
            ->visiblePour(auth()->user())
            // ✅ Consommation calculée en une requête pour la page (voir le modèle)
            ->addSelect(\App\Models\RegieAvance::selectsConsommations());
    }
}
