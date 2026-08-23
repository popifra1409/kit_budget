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
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_regie_avance') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_regie_avance') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_regie_avance') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_regie_avance')
            && in_array($record->statut, ['actif']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_regie_avance')
            && $record->statut === 'actif'
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

            // ── Section 3 : Observations ──────────────────────
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

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Dépensé')
                    ->money('XAF')->sortable()
                    ->color('warning')
                    ->tooltip('Somme des dépenses apurées par décaissement'),

                // ✅ NOUVEAU — IR collecté sur l'ensemble des décaissements
                Tables\Columns\TextColumn::make('montant_ir_collecte')
                    ->label('IR collecté')
                    ->getStateUsing(
                        fn($record) =>
                        $record->decaissements()->sum('montant_ir_collecte')
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
                    ->tooltip('= Décaissé − Dépensé'),

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
                            Forms\Components\Select::make('decaissement_id')
                                ->label('Décaissement / Tranche')
                                ->options(fn($record) => $record->decaissements()
                                    ->orderBy('numero')
                                    ->get()
                                    ->mapWithKeys(fn($d) => [$d->id => "{$d->numero} — {$d->libelle_tranche}"]))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $d = \App\Models\DecaissementRegie::find($state);
                                    if ($d) {
                                        $set('numero_tranche', $d->libelle_tranche);
                                        if ($d->date_decaissement) {
                                            $set('date_debut', $d->date_decaissement->format('Y-m-d'));
                                        }
                                    }
                                })
                                ->helperText('Seules les dépenses réellement imputées à ce décaissement seront incluses (traçabilité exacte, y compris pour un bon réparti sur plusieurs tranches).')
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
                                    'valide' => 'Validé',
                                ])
                                ->default(['paye', 'valide'])
                                ->columns(2)
                                ->required(),

                            Forms\Components\TextInput::make('numero_tranche')
                                ->label('N° de tranche / désignation')
                                ->placeholder('Ex: QUATRIÈME ENCAISSE'),

                            Forms\Components\Textarea::make('texte_apurement')
                                ->label('Pièces justificatives (une ligne par numéro)')
                                ->rows(10)
                                ->placeholder(
                                    "Résolution N°... portant ouverture de la Régie...\n" .
                                        "Décision N°... pour l'achat des réactifs...\n" .
                                        "Décision de Déblocage N°...\n" .
                                        "Demande d'autorisation du...\n" .
                                        "Certificat d'Engagement N°...\n" .
                                        "Quittance de Reversement du solde de gestion...\n" .
                                        "Quittance de Reversement de l'IR N°...\n" .
                                        "Tableau des justificatifs de dépenses de l'encaisse N°..."
                                )
                                ->helperText('Chaque ligne sera numérotée automatiquement dans le document')
                                ->columnSpanFull(),
                        ])
                        ->action(function ($record, array $data) {
                            // ✅ Récupération de l'EtatConfig
                            $etatConfig = \App\Models\EtatConfig::where('code', 'etat_compte_emploi_regie')
                                ->where('est_defaut', true)
                                ->first();

                            $decaissement = \App\Models\DecaissementRegie::with('provisions')->find($data['decaissement_id']);
                            $provisionIds = $decaissement?->provisions->pluck('id') ?? collect();
                            $statutsFiltre = $data['statuts'] ?? ['paye', 'valide'];

                            // ✅ Basé sur provision_consommations (traçabilité réelle) —
                            //    un BCR réparti sur plusieurs tranches n'apparaît ici que
                            //    pour la part réellement prise sur CE décaissement.
                            $depenses = \App\Models\ProvisionConsommation::whereIn('provision_ligne_regie_id', $provisionIds)
                                ->with('bonCommandeRegie.fournisseur')
                                ->get()
                                ->map(function ($c) {
                                    $bcr = $c->bonCommandeRegie;
                                    if (!$bcr) return null;

                                    $ratio = (float) $bcr->montant_ttc > 0
                                        ? ((float) $c->montant / (float) $bcr->montant_ttc)
                                        : 0;

                                    return (object) [
                                        'numero'         => $bcr->numero_facture_definitive
                                            ? $bcr->numero_facture_definitive . ($ratio < 0.999 ? ' (part.)' : '')
                                            : '⚠️ ' . $bcr->numero . ' (facture non renseignée)',
                                        'statut'         => $bcr->statut,
                                        'date_emission'  => $bcr->date_emission,
                                        'fournisseur'    => $bcr->fournisseur,
                                        'montant_ttc'    => round((float) $bcr->montant_ttc * $ratio, 2),
                                        'montant_tva'    => round((float) $bcr->montant_tva * $ratio, 2),
                                        'montant_ir'     => round((float) $bcr->montant_ir * $ratio, 2),
                                        'net_a_payer'    => round((float) $bcr->net_a_payer * $ratio, 2),
                                    ];
                                })
                                ->filter()
                                ->filter(fn($d) => in_array($d->statut, $statutsFiltre))
                                ->filter(function ($d) use ($data) {
                                    if (!$d->date_emission) return true;
                                    $de = \Carbon\Carbon::parse($d->date_emission);
                                    return $de->between($data['date_debut'], $data['date_fin']);
                                })
                                ->sortBy('date_emission')
                                ->values();

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

                            // ✅ $donnees structuré comme tous les autres documents
                            $donnees = [
                                '_raw'            => $record,
                                '_etat_config'    => $etatConfig,
                                'depenses'        => $depenses,
                                'lignesApurement' => $lignesApurement,
                                'numeroTranche'   => $data['numero_tranche'] ?? '',
                                'dateDebut'       => \Carbon\Carbon::parse($data['date_debut']),
                                'dateFin'         => \Carbon\Carbon::parse($data['date_fin']),
                                'totaux'          => $totaux,
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
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $d = \App\Models\DecaissementRegie::find($state);
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
                                    'valide' => 'Validé',
                                ])
                                ->default(['paye', 'valide'])
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

                            $decaissement = \App\Models\DecaissementRegie::with('provisions')->find($data['decaissement_id']);
                            $provisionIds = $decaissement?->provisions->pluck('id') ?? collect();
                            $statutsFiltre = $data['statuts'] ?? ['paye', 'valide'];

                            // ✅ Basé sur provision_consommations (traçabilité réelle) —
                            //    un BCR réparti sur plusieurs tranches n'apparaît ici que
                            //    pour la part réellement prise sur CE décaissement.
                            $depenses = \App\Models\ProvisionConsommation::whereIn('provision_ligne_regie_id', $provisionIds)
                                ->with('bonCommandeRegie.fournisseur')
                                ->get()
                                ->map(function ($c) {
                                    $bcr = $c->bonCommandeRegie;
                                    if (!$bcr) return null;

                                    $ratio = (float) $bcr->montant_ttc > 0
                                        ? ((float) $c->montant / (float) $bcr->montant_ttc)
                                        : 0;

                                    return (object) [
                                        'numero'         => $bcr->numero_facture_definitive
                                            ? $bcr->numero_facture_definitive . ($ratio < 0.999 ? ' (part.)' : '')
                                            : '⚠️ ' . $bcr->numero . ' (facture non renseignée)',
                                        'statut'         => $bcr->statut,
                                        'date_emission'  => $bcr->date_emission,
                                        'fournisseur'    => $bcr->fournisseur,
                                        'montant_ttc'    => round((float) $bcr->montant_ttc * $ratio, 2),
                                        'montant_tva'    => round((float) $bcr->montant_tva * $ratio, 2),
                                        'montant_ir'     => round((float) $bcr->montant_ir * $ratio, 2),
                                        'net_a_payer'    => round((float) $bcr->net_a_payer * $ratio, 2),
                                    ];
                                })
                                ->filter()
                                ->filter(fn($d) => in_array($d->statut, $statutsFiltre))
                                ->filter(fn($d) => $d->montant_ir > 0)
                                ->filter(function ($d) use ($data) {
                                    if (!$d->date_emission) return true;
                                    $de = \Carbon\Carbon::parse($d->date_emission);
                                    return $de->between($data['date_debut'], $data['date_fin']);
                                })
                                ->sortBy('date_emission')
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
        $query = parent::getEloquentQuery()
            ->where('type', 'rav')
            ->with(['exercice', 'responsable', 'budget']);

        $user = auth()->user();

        if (!$user) return $query->whereRaw('1 = 0');

        // ✅ Supervision : voit tout
        if ($user->hasAnyRole(['super_admin', 'admin', 'daaf', 'agent_comptable'])) {
            return $query;
        }

        // ✅ Permission view_any = voit toutes les régies
        if ($user->can('view_any_regie_avance')) {
            return $query;
        }

        // ✅ Autres : uniquement ses régies (responsable)
        return $query->where('responsable_id', $user->id);
    }
}
