<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\MemoireDepenseResource\Pages;
use App\Models\MemoireDepense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\ActionsPosition;

class MemoireDepenseResource extends Resource
{
    protected static ?string $model           = MemoireDepense::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mémoires de Dépenses';
    protected static ?string $modelLabel      = 'Mémoire de Dépense';
    protected static ?string $pluralModelLabel = 'Mémoires de Dépenses';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int    $navigationSort  = 30;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_memoire_depense');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_memoire_depense');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_memoire_depense');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('update_memoire_depense')) return false;
        return $record->statut === 'brouillon';
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('delete_memoire_depense')) return false;
        return $record->statut === 'brouillon';
    }

    public static function canValider($record): bool
    {
        return auth()->check() && auth()->user()->can('valider_memoire_depense');
    }

    public static function canTransformerEnDa($record): bool
    {
        return auth()->check() && auth()->user()->can('transformer_memoire_depense_en_da');
    }

    // =========================================================================
    // FORMULAIRE
    // =========================================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations Générales')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->default(fn() => MemoireDepense::genererNumero(now()->year))
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\DatePicker::make('date_memoire')
                            ->label('Date du Mémoire')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice')
                            ->numeric()
                            ->default(now()->year)
                            ->required(),
                    ]),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de la Dépense')
                        ->required()
                        ->rows(2),
                ])
                ->columns(1),

            Forms\Components\Section::make('Références')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('numero_decision')->label('N° Décision'),
                        Forms\Components\DatePicker::make('date_decision')->label('Date Décision'),
                        Forms\Components\TextInput::make('numero_ce')->label("N° Certificat d'Engagement"),
                        Forms\Components\DatePicker::make('date_ce')->label('Date CE'),
                    ]),
                ])
                ->collapsed(),

            Forms\Components\Section::make('Lignes de Dépenses')
                ->description('Choisissez le mode de saisie pour toutes les lignes.')
                ->schema([

                    // ── Mode de saisie global ─────────────────────────────
                    Forms\Components\Grid::make(6)->schema([

                        Forms\Components\ToggleButtons::make('mode_saisie_global')
                            ->label('Mode de saisie')
                            ->options([
                                'montant_nap'   => '📊 Montant NAP',
                                'prix_unitaire' => '💰 Prix Unitaire HT',
                            ])
                            ->default('montant_nap')
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                $component->state($record?->mode_saisie ?? 'montant_nap');
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // ✅ Synchroniser avec le champ persisté
                                $set('mode_saisie', $state);
                            })
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('taux_tva_global')
                            ->label('TVA globale (%)')
                            ->numeric()
                            ->default(19.25)
                            ->suffix('%')
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->helperText("S'applique à toutes les lignes")
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->lignes->isNotEmpty()) {
                                    $component->state($record->lignes->first()->taux_tva ?? 19.25);
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('taux_ir_global')
                            ->label('IR global (%)')
                            ->numeric()
                            ->default(5.5)
                            ->suffix('%')
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->helperText("S'applique à toutes les lignes")
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->lignes->isNotEmpty()) {
                                    $component->state($record->lignes->first()->taux_ir ?? 5.5);
                                }
                            })
                            ->columnSpan(1),

                    ])->columnSpanFull(),

                    Forms\Components\Hidden::make('mode_saisie')
                        ->default('montant_nap')
                        ->dehydrated(true),

                    // ── Repeater lignes ───────────────────────────────────
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(12)->schema([

                                // Nature
                                Forms\Components\TextInput::make('nature_depense')
                                    ->label('Nature de la dépense (Désignation)')
                                    ->required()
                                    ->columnSpan(3),

                                // Quantité
                                Forms\Components\TextInput::make('quantite')
                                    ->label('Qté')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                // ── Mode Prix Unitaire HT ──────────────────
                                Forms\Components\TextInput::make('prix_unitaire')
                                    ->label('Prix Unit. HT')
                                    ->numeric()
                                    ->suffix('FCFA')
                                    ->default(0)
                                    ->required()
                                    ->hidden(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') === 'montant_nap'
                                    )
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                // ── Mode NAP unitaire ──────────────────────
                                // ✅ Ce champ est la BASE du calcul en mode NAP
                                // Il ne doit PAS être modifié par les calculs
                                Forms\Components\TextInput::make('montant_nap_input')
                                    ->label('NAP unitaire')
                                    ->numeric()
                                    ->suffix('FCFA')
                                    ->required(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') === 'montant_nap'
                                    )
                                    ->hidden(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') !== 'montant_nap'
                                    )
                                    ->live(onBlur: true)
                                    // ✅ Calcule prix_unitaire en interne pour la persistence
                                    // mais NE modifie PAS montant_nap_input
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $nap    = floatval($state ?? 0);
                                        $qte    = floatval($get('quantite') ?? 1);
                                        $tauxIr = floatval($get('../../taux_ir_global') ?? 5.5);

                                        if ($nap > 0 && $qte > 0 && $tauxIr < 100) {
                                            // MHT = NAP_total / (1 - IR/100)
                                            $napTotal = $nap * $qte;
                                            $mht      = $napTotal / (1 - ($tauxIr / 100));
                                            $pu       = round($mht / $qte, 4);
                                            // ✅ Stocker PU en interne — montant_nap_input reste intact
                                            $set('prix_unitaire', $pu);
                                        }
                                    })
                                    ->dehydrated(true)
                                    ->helperText('Net à payer par unité — base du calcul')
                                    ->columnSpan(2),

                                // ── Previews calculés ──────────────────────
                                Forms\Components\Placeholder::make('prev_mht')
                                    ->label('MHT')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['mht'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_tva')
                                    ->label('TVA')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['tva'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ttc')
                                    ->label('TTC')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['ttc'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ir')
                                    ->label('IR')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['ir'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                // ✅ NAP affiché = montant_nap_input × quantité (mode NAP)
                                // ou MHT - IR (mode PU) — jamais recalculé depuis TTC
                                Forms\Components\Placeholder::make('prev_nap')
                                    ->label('NAP total')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['nap'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn(array $data, Get $get) =>
                            static::preparerDonneesLigne($data, $get)
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn(array $data, Get $get) =>
                            static::preparerDonneesLigne($data, $get)
                        )
                        ->orderColumn('numero_ligne')
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter une ligne')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(
                            fn(array $state): ?string =>
                            $state['nature_depense'] ?? null
                        ),
                ])
                ->columns(1),

            Forms\Components\Section::make('Signature')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('signataire_nom')
                            ->label('Nom du Signataire'),
                        Forms\Components\TextInput::make('signataire_fonction')
                            ->label('Fonction')
                            ->default('LE DIRECTEUR GENERAL'),
                        Forms\Components\TextInput::make('lieu_signature')
                            ->label('Lieu')
                            ->default('Yaoundé'),
                    ]),
                ])
                ->collapsed(),

            Forms\Components\Section::make('Totaux')
                ->schema([
                    Forms\Components\Placeholder::make('totaux')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || !$record->exists) {
                                return 'Les totaux seront calculés automatiquement après sauvegarde';
                            }
                            return view('filament.components.memoire-totaux', [
                                'memoire' => $record,
                            ]);
                        }),
                ])
                ->visible(fn($record) => $record && $record->exists)
                ->collapsed(),
        ]);
    }

    // =========================================================================
    // CALCULER MONTANTS — previews dans le Repeater
    // =========================================================================
    protected static function calculerMontants(Get $get): array
    {
        $zero = ['mht' => 0, 'tva' => 0, 'ttc' => 0, 'ir' => 0, 'nap' => 0];

        $mode   = $get('../../mode_saisie_global')   ?? 'montant_nap';
        $qte    = floatval($get('quantite')           ?? 0);
        $tauxTv = floatval($get('../../taux_tva_global') ?? 19.25);
        $tauxIr = floatval($get('../../taux_ir_global')  ?? 5.5);

        if ($qte <= 0) return $zero;

        // ── Mode NAP ──────────────────────────────────────────────
        // ✅ Le NAP est la BASE — il ne subit aucun recalcul
        // Formule : MHT = NAP_total / (1 - IR/100)
        if ($mode === 'montant_nap') {
            $napUnit = floatval($get('montant_nap_input') ?? 0);
            if ($napUnit <= 0) return $zero;

            $napTotal = $napUnit * $qte;

            // Garde-fou division par zéro
            if ($tauxIr >= 100) return $zero;

            $mht = $napTotal / (1 - ($tauxIr / 100));
            $tva = $mht * ($tauxTv / 100);
            $ttc = $mht + $tva;
            $ir  = $mht * ($tauxIr / 100);

            // ✅ NAP affiché = valeur saisie × quantité (intact, non recalculé)
            return [
                'mht' => round($mht, 2),
                'tva' => round($tva, 2),
                'ttc' => round($ttc, 2),
                'ir'  => round($ir, 2),
                'nap' => round($napTotal, 2), // ← NAP = ce que l'utilisateur a saisi × qté
            ];
        }

        // ── Mode Prix Unitaire HT ─────────────────────────────────
        // Formule : NAP = MHT - IR
        $pu = floatval($get('prix_unitaire') ?? 0);
        if ($pu <= 0) return $zero;

        $mht = $qte * $pu;
        $tva = $mht * ($tauxTv / 100);
        $ttc = $mht + $tva;
        $ir  = $mht * ($tauxIr / 100);
        $nap = $mht - $ir; // ← NAP = résultat du calcul depuis PU

        return [
            'mht' => round($mht, 2),
            'tva' => round($tva, 2),
            'ttc' => round($ttc, 2),
            'ir'  => round($ir, 2),
            'nap' => round($nap, 2),
        ];
    }

    // =========================================================================
    // PRÉPARER DONNÉES LIGNE — persistence en base
    // =========================================================================
    protected static function preparerDonneesLigne(array $data, Get $get): array
    {
        $tauxTva = floatval($get('taux_tva_global') ?? 19.25);
        $tauxIr  = floatval($get('taux_ir_global')  ?? 5.5);

        $data['taux_tva'] = $tauxTva;
        $data['taux_ir']  = $tauxIr;

        $qte    = floatval($data['quantite']          ?? 1);
        $nap    = floatval($data['montant_nap_input'] ?? 0);
        $pu     = floatval($data['prix_unitaire']     ?? 0);

        // ── Mode NAP ──────────────────────────────────────────────
        // ✅ Calculer prix_unitaire depuis NAP pour la persistence
        // mais ne pas modifier montant_nap_input
        if ($nap > 0 && $qte > 0 && $tauxIr < 100) {
            $napTotal              = $nap * $qte;
            $mht                   = $napTotal / (1 - ($tauxIr / 100));
            $data['prix_unitaire'] = round($mht / $qte, 4);
            $pu                    = $data['prix_unitaire'];
        } elseif ($pu <= 0) {
            $data['prix_unitaire'] = 0;
            $pu                    = 0;
        }

        // ── Calculer et stocker tous les montants en base ─────────
        $mht = $qte * $pu;
        $tva = round($mht * ($tauxTva / 100), 2);
        $ttc = round($mht + $tva, 2);
        $ir  = round($mht * ($tauxIr / 100), 2);

        // ✅ NAP stocké = valeur saisie × quantité (mode NAP)
        //             ou MHT - IR (mode PU)
        $napPersiste = ($nap > 0)
            ? round($nap * $qte, 2)   // Mode NAP : valeur saisie × qté
            : round($mht - $ir, 2);   // Mode PU  : calculé

        $data['montant_ht']  = round($mht, 2);
        $data['montant_tva'] = $tva;
        $data['montant_ttc'] = $ttc;
        $data['montant_ir']  = $ir;
        $data['montant_net'] = $napPersiste;

        // Nettoyer le champ temporaire
        unset($data['montant_nap_input']);

        return $data;
    }

    // =========================================================================
    // TABLE
    // =========================================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')
                    ->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_memoire')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->searchable()
                    ->tooltip(fn($record) => $record->objet),

                // ✅ TTC = somme des montant_ttc des lignes
                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')->sortable()->alignEnd()
                    ->getStateUsing(
                        fn($record) =>
                        $record->lignes->sum('montant_ttc')
                            ?: $record->montant_ttc
                            ?: 0
                    ),

                // ✅ NAP = somme des montant_net des lignes
                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant NAP')
                    ->money('XAF')->sortable()->weight('bold')->alignEnd()
                    ->getStateUsing(
                        fn($record) =>
                        $record->lignes->sum('montant_net')
                            ?: $record->montant_net
                            ?: 0
                    ),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')->counts('lignes')->badge()->color('info'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success'   => 'valide',
                        'info'      => 'transmis',
                        'warning'   => 'transforme',
                        'danger'    => 'annule',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon'  => 'Brouillon',
                        'valide'     => 'Validé',
                        'transmis'   => 'Transmis',
                        'transforme' => 'Transformé en DA',
                        'annule'     => 'Annulé',
                    ]),

                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période')
                            ->options([
                                'today'        => "Aujourd'hui",
                                'yesterday'    => 'Hier',
                                'this_week'    => 'Cette semaine',
                                'last_week'    => 'Semaine dernière',
                                'this_month'   => 'Ce mois',
                                'last_month'   => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year'    => 'Cette année',
                                'last_year'    => 'Année dernière',
                            ])
                            ->default('today')
                            ->placeholder('Toutes les périodes'),
                    ])
                    ->default(['periode' => 'today'])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['periode'] ?? null,
                            fn($q, $periode) => match ($periode) {
                                'today'        => $q->whereDate('date_memoire', today()),
                                'yesterday'    => $q->whereDate('date_memoire', today()->subDay()),
                                'this_week'    => $q->whereBetween('date_memoire', [now()->startOfWeek(), now()->endOfWeek()]),
                                'last_week'    => $q->whereBetween('date_memoire', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                                'this_month'   => $q->whereMonth('date_memoire', now()->month)->whereYear('date_memoire', now()->year),
                                'last_month'   => $q->whereMonth('date_memoire', now()->subMonth()->month)->whereYear('date_memoire', now()->subMonth()->year),
                                'this_quarter' => $q->whereBetween('date_memoire', [now()->startOfQuarter(), now()->endOfQuarter()]),
                                'last_quarter' => $q->whereBetween('date_memoire', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                                'this_year'    => $q->whereYear('date_memoire', now()->year),
                                'last_year'    => $q->whereYear('date_memoire', now()->subYear()->year),
                                default        => $q,
                            }
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) return null;
                        $labels = [
                            'today'        => "Aujourd'hui",
                            'yesterday'    => 'Hier',
                            'this_week'    => 'Cette semaine',
                            'last_week'    => 'Semaine dernière',
                            'this_month'   => 'Ce mois',
                            'last_month'   => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year'    => 'Cette année',
                            'last_year'    => 'Année dernière',
                        ];
                        return 'Période : ' . ($labels[$data['periode']] ?? $data['periode']);
                    }),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['du'], fn($q, $v) => $q->whereDate('date_memoire', '>=', $v))
                            ->when($data['au'], fn($q, $v) => $q->whereDate('date_memoire', '<=', $v))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['du'] ?? null)
                            $indicators[] = Tables\Filters\Indicator::make(
                                'Du ' . \Carbon\Carbon::parse($data['du'])->format('d/m/Y')
                            )->removeField('du');
                        if ($data['au'] ?? null)
                            $indicators[] = Tables\Filters\Indicator::make(
                                'Au ' . \Carbon\Carbon::parse($data['au'])->format('d/m/Y')
                            )->removeField('au');
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('apercu')
                        ->label('Aperçu')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading(fn($record) => 'Aperçu — ' . $record->numero)
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer')
                        ->modalContent(function ($record) {
                            $record->load('lignes');
                            return view('filament.modals.apercu-memoire-depense', [
                                'memoire' => $record,
                            ]);
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->visible(fn($record) => $record->statut !== 'brouillon')
                        ->url(fn($record) => route('memoire-depense.pdf', $record))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(
                            fn($record) =>
                            $record->statut === 'brouillon'
                                && static::canValider($record)
                        )
                        ->action(function ($record) {
                            $totalTtc = $record->lignes->sum('montant_ttc');
                            $totalNap = $record->lignes->sum('montant_net');
                            $totalIr  = $record->lignes->sum('montant_ir');
                            $totalTva = $record->lignes->sum('montant_tva');

                            $record->update([
                                'statut'      => 'valide',
                                'montant_ttc' => $totalTtc,
                                'montant_net' => $totalNap,
                                'montant_ir'  => $totalIr,
                                'montant_tva' => $totalTva,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Mémoire validé')
                                ->body("Le mémoire {$record->numero} a été validé. "
                                    . "Montant TTC : " . number_format($totalTtc, 0, ',', ' ') . " FCFA")
                                ->send();
                        }),

                    Tables\Actions\Action::make('transformer_en_da')
                        ->label('→ DA')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('primary')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'valide'
                                && !$record->decision_administrative_id
                                && static::canTransformerEnDa($record)
                        )
                        ->url(fn($record) => static::getUrl('view', ['record' => $record])),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMemoireDepenses::route('/'),
            'create' => Pages\CreateMemoireDepense::route('/create'),
            'view'   => Pages\ViewMemoireDepense::route('/{record}'),
            'edit'   => Pages\EditMemoireDepense::route('/{record}/edit'),
        ];
    }
}
