<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\AchatDirectResource\Pages;
use App\Models\DepenseRegie;
use App\Models\RegieAvance;
use App\Models\ProvisionLigneRegie;
use App\Models\ParametresStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AchatDirectResource extends Resource
{
    protected static ?string $model           = DepenseRegie::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Achats Directs';
    protected static ?string $modelLabel      = 'Achat Direct';
    protected static ?string $pluralModelLabel = 'Achats Directs';
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $slug            = 'achats-directs';
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_depense_regie') ?? false;
    }
    public static function canView($record): bool
    {
        return auth()->user()?->can('view_depense_regie') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_depense_regie') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_depense_regie')
            && $record->statut === 'brouillon';
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_depense_regie')
            && $record->statut === 'brouillon';
    }

    // =========================================================
    // FORMULAIRE — identique au RelationManager
    // =========================================================
    public static function form(Form $form): Form
    {
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        Forms\Components\DatePicker::make('date_depense')
                            ->label('Date')->default(now())->required(),

                        Forms\Components\Hidden::make('type_depense')
                            ->default('achat_direct'),
                    ]),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet de la dépense')
                        ->required()->maxLength(255)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Régie et ligne budgétaire')
                ->schema([
                    Forms\Components\Select::make('regie_avance_id')
                        ->label('Régie / Menu Dépense')
                        ->options(function () {
                            return RegieAvance::where('statut', 'actif')
                                ->when(
                                    !auth()->user()?->hasAnyRole([
                                        'super_admin',
                                        'admin',
                                        'daaf',
                                        'agence_comptable'
                                    ]),
                                    fn($q) => $q->where('responsable_id', auth()->id())
                                )
                                ->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "{$r->numero} — {$r->libelle} ({$r->label_type})"
                                ]);
                        })
                        ->required()->searchable()->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('provision_ligne_regie_id', null);
                            $set('ligne_regie_avance_id',   null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('provision_ligne_regie_id')
                        ->label('Provision disponible (ligne dérivée)')
                        ->options(function (Get $get) {
                            $regieId = $get('regie_avance_id');
                            if (!$regieId) return [];

                            return ProvisionLigneRegie::whereHas(
                                'decaissement',
                                fn($q) =>
                                $q->where('regie_avance_id', $regieId)
                                    ->where('statut', 'verse')
                            )
                                ->where('montant_disponible', '>', 0)
                                ->with(['ligneRegie.nomenclature', 'decaissement'])
                                ->get()
                                ->mapWithKeys(fn($p) => [
                                    $p->id =>
                                    "{$p->ligneRegie->nomenclature->code} — "
                                        . "{$p->ligneRegie->nomenclature->libelle} "
                                        . "| {$p->decaissement->libelle_tranche} "
                                        . "| Dispo: "
                                        . number_format($p->montant_disponible, 0, ',', ' ')
                                        . " FCFA"
                                ]);
                        })
                        ->required()->searchable()->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $prov = ProvisionLigneRegie::find($state);
                            $set('ligne_regie_avance_id', $prov?->ligne_regie_avance_id);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('ligne_regie_avance_id'),

                    // ✅ Aperçu dark-mode compatible
                    Forms\Components\Placeholder::make('apercu_provision')
                        ->label('Situation de la provision')
                        ->content(function (Get $get) {
                            $provId = $get('provision_ligne_regie_id');
                            if (!$provId) return '← Sélectionnez une provision';

                            $prov = ProvisionLigneRegie::with([
                                'ligneRegie.nomenclature',
                                'decaissement',
                            ])->find($provId);
                            if (!$prov) return '—';

                            return new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg p-3 text-sm leading-loose '
                                    . 'bg-slate-100 dark:bg-slate-800 '
                                    . 'text-slate-800 dark:text-slate-200">'
                                    . '<table class="w-full">'
                                    . '<tr><td class="font-semibold pr-4 w-32">Nomenclature :</td>'
                                    . '<td>' . $prov->ligneRegie->nomenclature->code
                                    . ' — ' . $prov->ligneRegie->nomenclature->libelle . '</td></tr>'
                                    . '<tr><td class="font-semibold pr-4">Tranche :</td>'
                                    . '<td>' . $prov->decaissement->libelle_tranche . '</td></tr>'
                                    . '<tr><td class="font-semibold pr-4">Disponible :</td>'
                                    . '<td class="text-green-600 dark:text-green-400 font-bold">'
                                    . number_format($prov->montant_disponible, 0, ',', ' ')
                                    . ' FCFA</td></tr>'
                                    . '</table></div>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Fournisseur')
                ->schema([
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur référencé')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->searchable()->preload()->nullable()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('fournisseur_libre')
                        ->label('Ou fournisseur libre')
                        ->maxLength(255)->columnSpan(1),
                ])
                ->columns(3),

            Forms\Components\Section::make('Montants')
                ->description('Saisissez le Net à Payer — les autres montants sont calculés automatiquement.')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\ToggleButtons::make('mode_saisie_montant')
                            ->label('Mode de saisie')
                            ->options([
                                'nap'  => '📊 Net à Payer (NAP)',
                                'brut' => '💰 Montant HT',
                            ])
                            ->default('nap')->inline()->live()->dehydrated(false),

                        Forms\Components\Placeholder::make('nap_max_suggere')
                            ->label('💡 Limites de saisie')
                            ->content(function (Get $get) {
                                $seuilAd  = (float) (ParametresStructure::where('actif', true)
                                    ->value('seuil_achat_direct_regie') ?? 500000);
                                $seuilBcr = (float) (ParametresStructure::where('actif', true)
                                    ->value('seuil_bon_commande_regie') ?? 5000000);

                                $tauxTv = (float) ($get('taux_tva') ?? 19.25);
                                $tauxIr = (float) ($get('taux_ir')  ?? 5.5);

                                // NAP max pour achat direct (TTC doit être STRICTEMENT < 500 000)
                                // On prend 499 999 comme TTC max effectif
                                $ttcMaxAd = $seuilAd - 1;
                                $napMaxAd = $ttcMaxAd
                                    * (1 - $tauxIr / 100)
                                    / (1 + $tauxTv / 100);

                                $ttcActuel = (float) ($get('montant_ttc') ?? 0);
                                $alertHtml = '';

                                if ($ttcActuel > 0) {
                                    if ($ttcActuel < $seuilAd) {
                                        $alertHtml = '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                            . 'bg-green-100 text-green-700 '
                                            . 'dark:bg-green-900/40 dark:text-green-300">'
                                            . '✅ Achat direct valide (TTC < ' . number_format($seuilAd, 0, ',', ' ') . ' FCFA)'
                                            . '</div>';
                                    } elseif ($ttcActuel < $seuilBcr) {
                                        $alertHtml = '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                            . 'bg-yellow-100 text-yellow-700 '
                                            . 'dark:bg-yellow-900/40 dark:text-yellow-300">'
                                            . '⚠️ Ce montant nécessite un BCR/BCM (TTC ≥ ' . number_format($seuilAd, 0, ',', ' ') . ' FCFA)'
                                            . '</div>';
                                    } else {
                                        $alertHtml = '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                            . 'bg-red-100 text-red-700 '
                                            . 'dark:bg-red-900/40 dark:text-red-300">'
                                            . '🚫 Dépasse le seuil BCR — procédure marché public requise '
                                            . '(TTC ≥ ' . number_format($seuilBcr, 0, ',', ' ') . ' FCFA)'
                                            . '</div>';
                                    }
                                }

                                return new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 text-sm leading-loose '
                                        . 'bg-blue-50 dark:bg-blue-900/30 '
                                        . 'text-blue-800 dark:text-blue-200 '
                                        . 'border border-blue-200 dark:border-blue-700">'
                                        . '<table class="w-full">'
                                        . '<tr class="font-semibold border-b border-blue-200 dark:border-blue-700">'
                                        . '<td>Type de dépense</td>'
                                        . '<td class="text-right">Seuil TTC</td>'
                                        . '<td class="text-right">NAP max</td>'
                                        . '</tr>'
                                        . '<tr class="text-green-700 dark:text-green-400">'
                                        . '<td>✅ Achat direct</td>'
                                        . '<td class="text-right">< ' . number_format($seuilAd, 0, ',', ' ') . ' FCFA</td>'
                                        . '<td class="text-right font-bold">'
                                        . '< ' . number_format($napMaxAd, 0, ',', ' ') . ' FCFA</td>'
                                        . '</tr>'
                                        . '<tr class="text-yellow-700 dark:text-yellow-400">'
                                        . '<td>📋 BCR / BCM</td>'
                                        . '<td class="text-right">≥ ' . number_format($seuilAd, 0, ',', ' ')
                                        . ' et < ' . number_format($seuilBcr, 0, ',', ' ') . ' FCFA</td>'
                                        . '<td class="text-right">—</td>'
                                        . '</tr>'
                                        . '<tr class="text-red-700 dark:text-red-400">'
                                        . '<td>🚫 Marché public</td>'
                                        . '<td class="text-right">≥ ' . number_format($seuilBcr, 0, ',', ' ') . ' FCFA</td>'
                                        . '<td class="text-right">—</td>'
                                        . '</tr>'
                                        . '</table>'
                                        . $alertHtml
                                        . '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('taux_tva')
                            ->label('TVA (%)')->numeric()->default(19.25)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\TextInput::make('taux_ir')
                            ->label('IR (%)')->numeric()->default(5.5)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('net_a_payer_input')
                            ->label('Net à Payer (FCFA)')
                            ->numeric()->prefix('FCFA')
                            ->required(fn(Get $get) => $get('mode_saisie_montant') !== 'brut')
                            ->hidden(fn(Get $get) => $get('mode_saisie_montant') === 'brut')
                            ->live(onBlur: true)->dehydrated(false)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\TextInput::make('montant_ht_input')
                            ->label('Montant HT (FCFA)')
                            ->numeric()->prefix('FCFA')
                            ->required(fn(Get $get) => $get('mode_saisie_montant') === 'brut')
                            ->hidden(fn(Get $get) => $get('mode_saisie_montant') !== 'brut')
                            ->live(onBlur: true)->dehydrated(false)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),
                    ]),

                    // ✅ Résumé dark-mode
                    Forms\Components\Placeholder::make('resume_calcul')
                        ->label('📊 Détail calculé')
                        ->content(function (Get $get) use ($seuil) {
                            $ht  = (float) ($get('montant_ht')  ?? 0);
                            $tva = (float) ($get('montant_tva') ?? 0);
                            $ttc = (float) ($get('montant_ttc') ?? 0);
                            $ir  = (float) ($get('montant_ir')  ?? 0);
                            $net = (float) ($get('net_a_payer') ?? 0);

                            if ($ht <= 0 && $net <= 0) {
                                return '← Saisissez un montant';
                            }

                            $depasseSeuil = $ttc > $seuil;
                            $alertHtml = $depasseSeuil
                                ? '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                . 'bg-red-100 text-red-700 '
                                . 'dark:bg-red-900/40 dark:text-red-300">'
                                . '⚠️ Montant TTC dépasse le seuil achat direct ('
                                . number_format($seuil, 0, ',', ' ')
                                . ' FCFA) — utilisez un BCR'
                                . '</div>'
                                : '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                . 'bg-green-100 text-green-700 '
                                . 'dark:bg-green-900/40 dark:text-green-300">'
                                . '✅ Dans la limite achat direct</div>';

                            return new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg p-3 text-sm '
                                    . 'bg-slate-50 dark:bg-slate-900 '
                                    . 'text-slate-800 dark:text-slate-200 '
                                    . 'border border-slate-200 dark:border-slate-700">'
                                    . '<table class="w-full leading-loose">'
                                    . '<tr><td>Montant HT :</td>'
                                    . '<td class="text-right font-semibold">'
                                    . number_format($ht, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr><td>TVA (' . ($get('taux_tva') ?? 19.25) . '%) :</td>'
                                    . '<td class="text-right">'
                                    . number_format($tva, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr class="border-t border-slate-300 dark:border-slate-600">'
                                    . '<td class="font-semibold">TTC :</td>'
                                    . '<td class="text-right font-bold">'
                                    . number_format($ttc, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr><td>IR (' . ($get('taux_ir') ?? 5.5) . '%) :</td>'
                                    . '<td class="text-right '
                                    . 'text-red-600 dark:text-red-400">'
                                    . number_format($ir, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr class="border-t-2 border-green-500 dark:border-green-400">'
                                    . '<td class="font-bold '
                                    . 'text-green-700 dark:text-green-400">Net à Payer :</td>'
                                    . '<td class="text-right font-bold text-base '
                                    . 'text-green-700 dark:text-green-400">'
                                    . number_format($net, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '</table>'
                                    . $alertHtml
                                    . '</div>'
                            );
                        })
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('montant_ht')->default(0),
                    Forms\Components\Hidden::make('montant_tva')->default(0),
                    Forms\Components\Hidden::make('montant_ttc')->default(0),
                    Forms\Components\Hidden::make('montant_ir')->default(0),
                    Forms\Components\Hidden::make('net_a_payer')->default(0),
                ]),

            Forms\Components\Section::make('Justificatif')
                ->schema([
                    Forms\Components\FileUpload::make('justificatif_fichier')
                        ->label('Pièce justificative')
                        ->disk('public')
                        ->directory('justificatifs-regies')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->nullable(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->columns(2)->collapsible()->collapsed(),
        ]);
    }

    // ── Calcul depuis NAP ou HT ────────────────────────────────
    protected static function recalculer(Get $get, Set $set): void
    {
        $mode   = $get('mode_saisie_montant') ?? 'nap';
        $tauxTv = (float) ($get('taux_tva') ?? 19.25);
        $tauxIr = (float) ($get('taux_ir')  ?? 5.5);

        if ($mode === 'nap') {
            $nap = (float) ($get('net_a_payer_input') ?? 0);
            if ($nap <= 0) return;
            $mht = $tauxIr > 0 ? round($nap / (1 - $tauxIr / 100), 2) : $nap;
        } else {
            $mht = (float) ($get('montant_ht_input') ?? 0);
            if ($mht <= 0) return;
        }

        $tva = round($mht * ($tauxTv / 100), 2);
        $ttc = round($mht + $tva, 2);
        $ir  = round($mht * ($tauxIr / 100), 2);
        $net = round($mht - $ir, 2);

        $set('montant_ht',  $mht);
        $set('montant_tva', $tva);
        $set('montant_ttc', $ttc);
        $set('montant_ir',  $ir);
        $set('net_a_payer', $net);
    }

    // =========================================================
    // TABLEAU
    // =========================================================
    public static function table(Table $table): Table
    {
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->weight('bold')->copyable()->searchable(),

                Tables\Columns\TextColumn::make('regieAvance.numero')
                    ->label('Régie')->badge()->color('info'),

                Tables\Columns\TextColumn::make('regieAvance.type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'rav'          => 'RAV',
                        'menu_depense' => 'MD',
                        default        => $state,
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'rav' ? 'primary' : 'warning'),

                Tables\Columns\TextColumn::make('date_depense')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(35)
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('ligneRegieAvance.nomenclature.code')
                    ->label('Nomenclature')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('fournisseur_affiche')
                    ->label('Fournisseur')
                    ->getStateUsing(
                        fn($record) =>
                        $record->fournisseur?->raison_sociale
                            ?? $record->fournisseur_libre
                            ?? '—'
                    )->limit(20),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')->money('XAF')->weight('bold')
                    ->color(
                        fn($record) =>
                        $record->montant_ttc > $seuil ? 'danger' : 'success'
                    )
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net à Payer')->money('XAF')
                    ->color('success')->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),

                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'gray'    => 'brouillon',
                        'warning' => 'valide',
                        'success' => 'paye',
                        'danger'  => 'annule',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon' => 'Brouillon',
                        'valide'    => 'Validé',
                        'paye'      => 'Payé',
                        'annule'    => 'Annulé',
                        default     => $state,
                    }),
            ])
            ->defaultSort('date_depense', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide'    => 'Validé',
                        'paye'      => 'Payé',
                        'annule'    => 'Annulé',
                    ]),

                Tables\Filters\SelectFilter::make('regie_avance_id')
                    ->label('Régie')
                    ->options(fn() => RegieAvance::pluck('libelle', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when(
                                $data['du'],
                                fn($q, $v) =>
                                $q->whereDate('date_depense', '>=', $v)
                            )
                            ->when(
                                $data['au'],
                                fn($q, $v) =>
                                $q->whereDate('date_depense', '<=', $v)
                            )
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        unset($data['net_a_payer_input'], $data['mode_saisie_montant']);
                        return $data;
                    }),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'brouillon'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            if ($record->provision_ligne_regie_id) {
                                ProvisionLigneRegie::findOrFail(
                                    $record->provision_ligne_regie_id
                                )->debiter($record->montant_ttc);
                            }
                            $record->update(['statut' => 'valide']);
                            Notification::make()
                                ->title('✅ Achat direct validé')
                                ->success()
                                ->body(
                                    "Net : " . number_format($record->net_a_payer, 0, ',', ' ')
                                        . " FCFA | IR : "
                                        . number_format($record->montant_ir, 0, ',', ' ') . " FCFA"
                                )->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')
                                ->danger()->body($e->getMessage())->persistent()->send();
                        }
                    }),

                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record?->statut, ['brouillon', 'valide'])
                            && auth()->user()?->can('annuler_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->statut === 'valide' && $record->provision_ligne_regie_id) {
                            ProvisionLigneRegie::find($record->provision_ligne_regie_id)
                                ?->crediter($record->montant_ttc);
                        }
                        $record->update([
                            'statut'       => 'annule',
                            'observations' => ($record->observations ?? '')
                                . "\n--- ANNULÉ " . now()->format('d/m/Y') . " ---\n"
                                . $data['motif'],
                        ]);
                        Notification::make()->title('Annulé')->warning()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type_depense', 'achat_direct')
            ->with(['regieAvance', 'fournisseur', 'ligneRegieAvance.nomenclature']);

        $user = auth()->user();
        if ($user && !$user->hasAnyRole([
            'super_admin',
            'admin',
            'daaf',
            'agence_comptable',
            'controleur_financier'
        ])) {
            $query->whereHas(
                'regieAvance',
                fn($q) =>
                $q->where('responsable_id', $user->id)
            );
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAchatsDirects::route('/'),
            'create' => Pages\CreateAchatDirect::route('/create'),
            'edit'   => Pages\EditAchatDirect::route('/{record}/edit'),
            'view'   => Pages\ViewAchatDirect::route('/{record}'),
        ];
    }
}
