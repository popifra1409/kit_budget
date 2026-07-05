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
    protected static ?string $model            = MemoireDepense::class;
    protected static ?string $navigationIcon   = 'heroicon-o-document-text';
    protected static ?string $navigationLabel  = 'Mémoires de Dépenses';
    protected static ?string $modelLabel       = 'Mémoire de Dépense';
    protected static ?string $pluralModelLabel = 'Mémoires de Dépenses';
    protected static ?string $navigationGroup  = 'Commandes & Engagement';
    protected static ?int    $navigationSort   = 30;

    // =========================================================================
    // PERMISSIONS
    // =========================================================================
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
        if (!auth()->check() || !auth()->user()->can('update_memoire_depense')) return false;
        return $record->statut === 'brouillon';
    }
    public static function canDelete($record): bool
    {
        if (!auth()->check() || !auth()->user()->can('delete_memoire_depense')) return false;
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

    protected static ?string $recordTitleAttribute = 'numero';
    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'objet'];
    }
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Montant TTC' => number_format($record->montant_ttc, 0, ',', ' ') . ' FCFA',
            'Montant NET' => number_format($record->montant_net, 0, ',', ' ') . ' FCFA',
            'Statut'      => $record->statut,
        ];
    }

    // =========================================================================
    // HELPER — recalcul partagé entre quantite et montant_nap_input
    //
    // ✅ FIX Qté : en extrayant ici, les deux champs utilisent la même logique.
    //    Filament re-render le Repeater quand un champ live change → sans ce hook
    //    sur quantite, la valeur revient au default(1) pour les previews.
    // =========================================================================
    protected static function recalculerPrixUnitaire(Get $get, Set $set): void
    {
        $napUnit = floatval($get('montant_nap_input')         ?? 0);
        $qte     = floatval($get('quantite')                  ?? 1);
        $tauxIr  = floatval($get('../../taux_ir_global')      ?? 5.5);

        if ($napUnit > 0 && $qte > 0 && $tauxIr < 100) {
            $napTotal = $napUnit * $qte;
            $mht      = $napTotal / (1 - ($tauxIr / 100));
            $set('prix_unitaire', round($mht / $qte, 4));
        } else {
            $set('prix_unitaire', 0);
        }
    }

    // =========================================================================
    // CALCULS — Source de vérité unique
    //
    // Mode NAP unitaire :
    //   NAP total = NAP unitaire × Qté
    //   MHT       = NAP total / (1 - IR%)   ex: / 0,945 si IR=5,5%
    //   IR        = MHT × IR%
    //   TVA       = MHT × TVA%
    //   TTC       = MHT + TVA
    //
    // Mode Prix Unitaire HT :
    //   MHT = PU × Qté
    //   TVA = MHT × TVA%
    //   TTC = MHT + TVA
    //   IR  = MHT × IR%
    //   NAP = MHT - IR
    // =========================================================================
    protected static function calculerMontants(Get $get): array
    {
        $zero = ['mht' => 0, 'tva' => 0, 'ttc' => 0, 'ir' => 0, 'nap' => 0];

        $mode    = $get('../../mode_saisie_global')    ?? 'montant_nap';
        $qte     = floatval($get('quantite')            ?? 0);
        $tauxTva = floatval($get('../../taux_tva_global') ?? 19.25);
        $tauxIr  = floatval($get('../../taux_ir_global')  ?? 5.5);

        if ($qte <= 0 || $tauxIr >= 100) return $zero;

        if ($mode === 'montant_nap') {
            $napUnit = floatval($get('montant_nap_input') ?? 0);
            if ($napUnit <= 0) return $zero;

            $napTotal = $napUnit * $qte;
            $mht      = $napTotal / (1 - ($tauxIr / 100));
            $ir       = $mht * ($tauxIr  / 100);
            $tva      = $mht * ($tauxTva / 100);
            $ttc      = $mht + $tva;

            return [
                'mht' => round($mht,      2),
                'ir'  => round($ir,       2),
                'tva' => round($tva,      2),
                'ttc' => round($ttc,      2),
                'nap' => round($napTotal, 2),
            ];
        }

        // Mode PU HT
        $pu = floatval($get('prix_unitaire') ?? 0);
        if ($pu <= 0) return $zero;

        $mht = $qte * $pu;
        $ir  = $mht * ($tauxIr  / 100);
        $tva = $mht * ($tauxTva / 100);
        $ttc = $mht + $tva;
        $nap = $mht - $ir;

        return [
            'mht' => round($mht, 2),
            'ir'  => round($ir,  2),
            'tva' => round($tva, 2),
            'ttc' => round($ttc, 2),
            'nap' => round($nap, 2),
        ];
    }

    // =========================================================================
    // PERSISTENCE — calcul direct depuis NAP (sans passer par PU arrondi)
    // =========================================================================
    protected static function preparerDonneesLigne(array $data, Get $get): array
    {
        $tauxTva = floatval($get('taux_tva_global') ?? 19.25);
        $tauxIr  = floatval($get('taux_ir_global')  ?? 5.5);

        $data['taux_tva'] = $tauxTva;
        $data['taux_ir']  = $tauxIr;

        $qte     = floatval($data['quantite']          ?? 1);
        $napUnit = floatval($data['montant_nap_input'] ?? 0);
        $pu      = floatval($data['prix_unitaire']     ?? 0);

        if ($qte <= 0) {
            $data['montant_ht'] = $data['montant_tva'] = $data['montant_ttc']
                = $data['montant_ir'] = $data['montant_net'] = 0;
            unset($data['montant_nap_input']);
            return $data;
        }

        if ($napUnit > 0 && $tauxIr < 100) {
            // ✅ Calcul direct depuis NAP — pas depuis PU arrondi
            $napTotal = $napUnit * $qte;
            $mht      = $napTotal / (1 - ($tauxIr / 100));
            $ir       = $mht * ($tauxIr  / 100);
            $tva      = $mht * ($tauxTva / 100);
            $ttc      = $mht + $tva;

            $data['prix_unitaire'] = round($mht / $qte, 4);
            $data['montant_ht']    = round($mht,      2);
            $data['montant_ir']    = round($ir,       2);
            $data['montant_tva']   = round($tva,      2);
            $data['montant_ttc']   = round($ttc,      2);
            $data['montant_net']   = round($napTotal,  2);
        } elseif ($pu > 0) {
            $mht = $qte * $pu;
            $ir  = $mht * ($tauxIr  / 100);
            $tva = $mht * ($tauxTva / 100);
            $ttc = $mht + $tva;
            $nap = $mht - $ir;

            $data['prix_unitaire'] = $pu;
            $data['montant_ht']    = round($mht, 2);
            $data['montant_ir']    = round($ir,  2);
            $data['montant_tva']   = round($tva, 2);
            $data['montant_ttc']   = round($ttc, 2);
            $data['montant_net']   = round($nap, 2);
        } else {
            $data['prix_unitaire'] = 0;
            $data['montant_ht'] = $data['montant_tva'] = $data['montant_ttc']
                = $data['montant_ir'] = $data['montant_net'] = 0;
        }

        unset($data['montant_nap_input']);
        return $data;
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
                            ->disabled()->dehydrated(),
                        Forms\Components\DatePicker::make('date_memoire')
                            ->label('Date du Mémoire')->default(now())->required(),
                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice')->numeric()->default(now()->year)->required(),
                    ]),
                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de la Dépense')->required()->rows(2),
                ])->columns(1),

            Forms\Components\Section::make('Références')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('numero_decision')->label('N° Décision'),
                        Forms\Components\DatePicker::make('date_decision')->label('Date Décision'),
                        Forms\Components\TextInput::make('numero_ce')->label("N° Certificat d'Engagement"),
                        Forms\Components\DatePicker::make('date_ce')->label('Date CE'),
                    ]),
                ])->collapsed(),

            Forms\Components\Section::make('Lignes de Dépenses')
                ->schema([

                    // ── Mode de saisie + taux globaux ──────────────────
                    Forms\Components\Grid::make(6)->schema([
                        Forms\Components\ToggleButtons::make('mode_saisie_global')
                            ->label('Mode de saisie')
                            ->options([
                                'montant_nap'   => '📊 NAP unitaire',
                                'prix_unitaire' => '💰 Prix Unitaire HT',
                            ])
                            ->default('montant_nap')->inline()->live()->dehydrated(false)
                            ->afterStateHydrated(
                                fn($component, $record) =>
                                $component->state($record?->mode_saisie ?? 'montant_nap')
                            )
                            ->afterStateUpdated(fn($state, Set $set) => $set('mode_saisie', $state))
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('taux_tva_global')
                            ->label('TVA globale (%)')
                            ->numeric()->default(19.25)->suffix('%')->required()->live()
                            ->dehydrated(false)
                            ->helperText('S\'applique à toutes les lignes')
                            ->afterStateHydrated(
                                fn($component, $record) =>
                                $record?->lignes->isNotEmpty()
                                    ? $component->state($record->lignes->first()->taux_tva ?? 19.25)
                                    : null
                            )
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('taux_ir_global')
                            ->label('IR global (%)')
                            ->numeric()->default(5.5)->suffix('%')->required()->live()
                            ->dehydrated(false)
                            ->helperText('Diviseur = 1 − IR%  (ex: 0,945 si 5,5%)')
                            ->afterStateHydrated(
                                fn($component, $record) =>
                                $record?->lignes->isNotEmpty()
                                    ? $component->state($record->lignes->first()->taux_ir ?? 5.5)
                                    : null
                            )
                            ->columnSpan(1),
                    ])->columnSpanFull(),

                    Forms\Components\Hidden::make('mode_saisie')->default('montant_nap')->dehydrated(true),

                    // ── Repeater lignes ────────────────────────────────
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(12)->schema([

                                Forms\Components\TextInput::make('nature_depense')
                                    ->label('Nature / Désignation')
                                    ->required()->columnSpan(3),

                                // ✅ FIX QTÉ — afterStateUpdated déclenche recalculerPrixUnitaire
                                //    Sans ça, Filament utilise default(1) pour les previews
                                //    quand un autre champ live déclenche un re-render
                                Forms\Components\TextInput::make('quantite')
                                    ->label('Qté')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        static::recalculerPrixUnitaire($get, $set)
                                    )
                                    ->columnSpan(1),

                                // ── Mode PU HT ──────────────────────────
                                Forms\Components\TextInput::make('prix_unitaire')
                                    ->label('P.U HT (FCFA)')
                                    ->numeric()->suffix('FCFA')->default(0)->required()
                                    ->hidden(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') === 'montant_nap'
                                    )
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                // ── Mode NAP unitaire ────────────────────
                                Forms\Components\TextInput::make('montant_nap_input')
                                    ->label('NAP/unité (FCFA)')
                                    ->numeric()
                                    ->suffix('FCFA')
                                    ->required(fn(Get $get) => $get('../../mode_saisie_global') === 'montant_nap')
                                    ->hidden(fn(Get $get)   => $get('../../mode_saisie_global') !== 'montant_nap')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        static::recalculerPrixUnitaire($get, $set)
                                    )
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        // Ne rien faire si déjà hydraté par mutateFormDataBeforeFill()
                                        if ($state !== null && $state > 0) return;

                                        // $record est la ligne de la relation (LigneMemoireDepense)
                                        if (!$record) return;

                                        $qte      = max(1, (float) ($record->quantite  ?? 1));
                                        $napTotal = (float) ($record->montant_net       ?? $record->net_a_payer ?? 0);

                                        // Fallback : calculer depuis MHT - IR si montant_net absent
                                        if ($napTotal <= 0 && ($record->montant_ht ?? 0) > 0) {
                                            $napTotal = (float) $record->montant_ht - (float) ($record->montant_ir ?? 0);
                                        }

                                        if ($napTotal > 0 && $qte > 0) {
                                            $component->state(round($napTotal / $qte, 4));
                                        }
                                    })
                                    ->dehydrated(true)
                                    ->helperText('Net à Percevoir par unité')
                                    ->columnSpan(2),

                                // ── Previews ─────────────────────────────
                                Forms\Components\Placeholder::make('prev_mht')
                                    ->label('MHT')
                                    ->content(
                                        fn(Get $get) =>
                                        number_format(static::calculerMontants($get)['mht'], 0, ',', ' ') . ' F'
                                    )->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_tva')
                                    ->label('TVA')
                                    ->content(
                                        fn(Get $get) =>
                                        number_format(static::calculerMontants($get)['tva'], 0, ',', ' ') . ' F'
                                    )->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ir')
                                    ->label('IR')
                                    ->content(
                                        fn(Get $get) =>
                                        number_format(static::calculerMontants($get)['ir'], 0, ',', ' ') . ' F'
                                    )->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ttc')
                                    ->label('TTC')
                                    ->content(
                                        fn(Get $get) =>
                                        number_format(static::calculerMontants($get)['ttc'], 0, ',', ' ') . ' F'
                                    )->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_nap')
                                    ->label('NAP total')
                                    ->content(
                                        fn(Get $get) =>
                                        number_format(static::calculerMontants($get)['nap'], 0, ',', ' ') . ' F'
                                    )->columnSpan(1),
                            ]),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn(array $data, Get $get) => static::preparerDonneesLigne($data, $get)
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn(array $data, Get $get) => static::preparerDonneesLigne($data, $get)
                        )
                        ->orderColumn('numero_ligne')
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter une ligne')
                        ->reorderable()->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['nature_depense'] ?? null),

                ])->columns(1),

            // ── Récapitulatif global ───────────────────────────────────────
            Forms\Components\Section::make('Récapitulatif des Totaux')
                ->schema([
                    Forms\Components\Placeholder::make('totaux_globaux')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || !$record->exists) {
                                return 'Les totaux seront calculés automatiquement après sauvegarde.';
                            }
                            $lignes = $record->lignes;
                            $rows = [
                                ['label' => 'Total MHT',       'value' => $lignes->sum('montant_ht'),  'color' => ''],
                                ['label' => 'Total TVA',       'value' => $lignes->sum('montant_tva'), 'color' => 'color:#854d0e;'],
                                ['label' => 'Total TTC',       'value' => $lignes->sum('montant_ttc'), 'color' => 'color:#1e40af;font-weight:bold;'],
                                ['label' => 'Total IR (retenue)', 'value' => $lignes->sum('montant_ir'), 'color' => 'color:#9f1239;'],
                                ['label' => 'Total NAP',       'value' => $lignes->sum('montant_net'), 'color' => 'color:#166534;font-weight:bold;'],
                            ];
                            $html = '<table style="border-collapse:collapse;width:auto;font-size:.85rem;">';
                            foreach ($rows as $row) {
                                $html .= '<tr>'
                                    . '<td style="padding:4px 16px 4px 0;font-weight:600;">' . $row['label'] . '</td>'
                                    . '<td style="padding:4px 0;text-align:right;' . $row['color'] . '">'
                                    . number_format($row['value'], 0, ',', ' ') . ' FCFA'
                                    . '</td></tr>';
                            }
                            $html .= '</table>';
                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ])
                ->visible(fn($record) => $record && $record->exists)
                ->collapsed(false),

            Forms\Components\Section::make('Signature')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('signataire_nom')->label('Nom du Signataire'),
                        Forms\Components\TextInput::make('signataire_fonction')
                            ->label('Fonction')->default('LE DIRECTEUR GENERAL'),
                        Forms\Components\TextInput::make('lieu_signature')
                            ->label('Lieu')->default('Yaoundé'),
                    ]),
                ])->collapsed(),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================
    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')->searchable()->sortable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('date_memoire')
                    ->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->searchable()->tooltip(fn($record) => $record->objet),
                Tables\Columns\TextColumn::make('total_mht')
                    ->label('MHT')->money('XAF')->alignEnd()
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_ht'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_tva')
                    ->label('TVA')->money('XAF')->alignEnd()->color('warning')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_tva'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_ir')
                    ->label('IR')->money('XAF')->alignEnd()->color('danger')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_ir'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_ttc')
                    ->label('Total TTC')->money('XAF')->sortable()->alignEnd()
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_ttc') ?: $record->montant_ttc ?: 0),
                Tables\Columns\TextColumn::make('total_nap')
                    ->label('Total NAP')->money('XAF')->sortable()->weight('bold')->alignEnd()->color('success')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_net') ?: $record->montant_net ?: 0),
                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')->counts('lignes')->badge()->color('info'),
                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success' => 'valide',
                        'info' => 'transmis',
                        'warning' => 'transforme',
                        'danger' => 'annule',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide' => 'Validé',
                        'transmis' => 'Transmis',
                        'transforme' => 'Transformé en DA',
                        'annule' => 'Annulé',
                    ]),
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période')
                            ->options([
                                'today' => "Aujourd'hui",
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
                            ->default('today')->placeholder('Toutes les périodes'),
                    ])
                    ->default(['periode' => 'today'])
                    ->query(function ($query, array $data) {
                        return $query->when($data['periode'] ?? null, fn($q, $p) => match ($p) {
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
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) return null;
                        $labels = [
                            'today' => "Aujourd'hui",
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
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['du'] ?? null,
                                fn($query, $value) => $query->whereDate('date_memoire', '>=', $value)
                            )
                            ->when(
                                $data['au'] ?? null,
                                fn($query, $value) => $query->whereDate('date_memoire', '<=', $value)
                            );
                    })
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
                        ->label('Aperçu')->icon('heroicon-o-eye')->color('info')
                        ->modalHeading(fn($record) => 'Aperçu — ' . $record->numero)
                        ->modalWidth('7xl')->modalSubmitAction(false)->modalCancelActionLabel('Fermer')
                        ->modalContent(fn($record) => view('filament.modals.apercu-memoire-depense', [
                            'memoire' => $record->load('lignes'),
                        ])),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')->icon('heroicon-o-document-arrow-down')->color('success')
                        ->visible(fn($record) => $record->statut !== 'brouillon')
                        ->url(fn($record) => route('memoire-depense.pdf', $record))->openUrlInNewTab(),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')->icon('heroicon-o-check-circle')->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->statut === 'brouillon' && static::canValider($record))
                        ->action(function ($record) {
                            $record->update([
                                'statut'      => 'valide',
                                'montant_ht'  => $record->lignes->sum('montant_ht'),
                                'montant_tva' => $record->lignes->sum('montant_tva'),
                                'montant_ir'  => $record->lignes->sum('montant_ir'),
                                'montant_ttc' => $record->lignes->sum('montant_ttc'),
                                'montant_net' => $record->lignes->sum('montant_net'),
                            ]);
                            Notification::make()->success()->title('Mémoire validé')
                                ->body("Mémoire {$record->numero} validé — TTC : "
                                    . number_format($record->lignes->sum('montant_ttc'), 0, ',', ' ') . " FCFA")
                                ->send();
                        }),

                    Tables\Actions\Action::make('transformer_en_da')
                        ->label('→ DA')->icon('heroicon-o-arrow-right-circle')->color('primary')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'valide' && !$record->decision_administrative_id
                                && static::canTransformerEnDa($record)
                        )
                        ->url(fn($record) => static::getUrl('view', ['record' => $record])),
                ])
                    ->label('Actions')->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')->button()->size('sm'),
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
