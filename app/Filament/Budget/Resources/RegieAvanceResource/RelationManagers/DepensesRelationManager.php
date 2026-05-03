<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use App\Models\ProvisionLigneRegie;
use App\Models\ParametresStructure;
use App\Models\RegieAvance;

class DepensesRelationManager extends RelationManager
{
    protected static string $relationship = 'depenses';
    protected static ?string $title       = 'Achats Directs';

    // =========================================================
    // HELPERS DARK MODE
    // =========================================================
    protected static function infoBox(string $html): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div class="rounded-lg p-3 text-sm leading-relaxed '
                . 'bg-slate-100 dark:bg-slate-800 '
                . 'text-slate-800 dark:text-slate-200">'
                . $html . '</div>'
        );
    }

    // =========================================================
    // SEUILS
    // =========================================================
    protected static function getSeuilAchatDirect(): float
    {
        return (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);
    }

    protected static function getSeuilBonCommande(): float
    {
        return (float) (ParametresStructure::where('actif', true)
            ->value('seuil_bon_commande_regie') ?? 5000000);
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public function form(Forms\Form $form): Forms\Form
    {
        $regie    = $this->getOwnerRecord();
        $seuilAd  = static::getSeuilAchatDirect();
        $seuilBcr = static::getSeuilBonCommande();

        return $form->schema([

            // ── Section 1 : Identification ────────────────────
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        Forms\Components\DatePicker::make('date_depense')
                            ->label('Date')
                            ->default(now())->required(),

                        Forms\Components\Hidden::make('type_depense')
                            ->default('achat_direct'),
                    ]),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet de la dépense')
                        ->required()->maxLength(255)->columnSpanFull(),
                ]),

            // ── Section 2 : Ligne budgétaire ──────────────────
            Forms\Components\Section::make('Ligne budgétaire à débiter')
                ->description('Sélectionnez la provision (ligne dérivée) qui sera débitée.')
                ->schema([

                    Forms\Components\Select::make('provision_ligne_regie_id')
                        ->label('Provision disponible')
                        ->options(function () use ($regie) {
                            return ProvisionLigneRegie::whereHas(
                                'decaissement',
                                fn($q) =>
                                $q->where('regie_avance_id', $regie->id)
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
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $prov = ProvisionLigneRegie::find($state);
                            $set('ligne_regie_avance_id', $prov?->ligne_regie_avance_id);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('ligne_regie_avance_id'),

                    // ✅ Aperçu provision — dark mode
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
                                    . '<tr>'
                                    . '<td class="font-semibold pr-4 w-36">Nomenclature :</td>'
                                    . '<td>'
                                    . $prov->ligneRegie->nomenclature->code
                                    . ' — '
                                    . $prov->ligneRegie->nomenclature->libelle
                                    . '</td></tr>'
                                    . '<tr>'
                                    . '<td class="font-semibold pr-4">Tranche :</td>'
                                    . '<td>' . $prov->decaissement->libelle_tranche . '</td>'
                                    . '</tr>'
                                    . '<tr>'
                                    . '<td class="font-semibold pr-4">Provisionné :</td>'
                                    . '<td>' . number_format($prov->montant_provisionne, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr>'
                                    . '<td class="font-semibold pr-4">Consommé :</td>'
                                    . '<td class="text-red-600 dark:text-red-400">'
                                    . number_format($prov->montant_consomme, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr>'
                                    . '<td class="font-semibold pr-4">Disponible :</td>'
                                    . '<td class="text-green-600 dark:text-green-400 font-bold">'
                                    . number_format($prov->montant_disponible, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '</table></div>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Fournisseur ────────────────────────
            Forms\Components\Section::make('Fournisseur')
                ->schema([
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur référencé')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->searchable()->preload()->nullable()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('fournisseur_libre')
                        ->label('Ou fournisseur libre')
                        ->maxLength(255)
                        ->placeholder('Nom si non référencé')
                        ->columnSpan(1),
                ])
                ->columns(3),

            // ── Section 4 : Montants (logique NAP) ────────────
            Forms\Components\Section::make('Montants')
                ->description(
                    'Saisissez le Net à Payer (NAP) — '
                        . 'les autres montants sont calculés automatiquement.'
                )
                ->schema([

                    // ── Mode de saisie ─────────────────────────
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\ToggleButtons::make('mode_saisie_montant')
                            ->label('Mode de saisie')
                            ->options([
                                'nap'  => '📊 Net à Payer (NAP)',
                                'brut' => '💰 Montant HT',
                            ])
                            ->default('nap')
                            ->inline()
                            ->live()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('taux_tva')
                            ->label('Taux TVA (%)')
                            ->numeric()->default(19.25)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\TextInput::make('taux_ir')
                            ->label('Taux IR (%)')
                            ->numeric()->default(5.5)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),
                    ]),

                    // ── Champs de saisie ───────────────────────
                    Forms\Components\Grid::make(2)->schema([

                        // Mode NAP
                        Forms\Components\TextInput::make('net_a_payer_input')
                            ->label('Net à Payer (FCFA)')
                            ->numeric()->prefix('FCFA')
                            ->required(
                                fn(Get $get) => ($get('mode_saisie_montant') ?? 'nap') === 'nap'
                            )
                            ->hidden(
                                fn(Get $get) => ($get('mode_saisie_montant') ?? 'nap') !== 'nap'
                            )
                            ->live(onBlur: true)
                            ->dehydrated(false)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            )
                            ->helperText(
                                'Montant net que percevra le fournisseur. '
                                    . 'Le TTC sera calculé automatiquement.'
                            ),

                        // Mode Brut HT
                        Forms\Components\TextInput::make('montant_ht_input')
                            ->label('Montant HT (FCFA)')
                            ->numeric()->prefix('FCFA')
                            ->required(
                                fn(Get $get) => ($get('mode_saisie_montant') ?? 'nap') === 'brut'
                            )
                            ->hidden(
                                fn(Get $get) => ($get('mode_saisie_montant') ?? 'nap') !== 'brut'
                            )
                            ->live(onBlur: true)
                            ->dehydrated(false)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            )
                            ->helperText('Montant hors taxes.'),
                    ]),

                    // ── Tableau des limites + résumé ───────────
                    Forms\Components\Placeholder::make('limites_et_resume')
                        ->label('📊 Limites et montants calculés')
                        ->content(function (Get $get) use ($seuilAd, $seuilBcr) {
                            $tauxTv = (float) ($get('taux_tva') ?? 19.25);
                            $tauxIr = (float) ($get('taux_ir')  ?? 5.5);

                            // NAP max pour achat direct (TTC < 500 000)
                            $ttcMaxAd = $seuilAd - 1;
                            $napMaxAd = $tauxIr > 0
                                ? round($ttcMaxAd * (1 - $tauxIr / 100) / (1 + $tauxTv / 100), 0)
                                : round($ttcMaxAd / (1 + $tauxTv / 100), 0);

                            // Valeurs calculées actuelles
                            $ht  = (float) ($get('montant_ht')  ?? 0);
                            $tva = (float) ($get('montant_tva') ?? 0);
                            $ttc = (float) ($get('montant_ttc') ?? 0);
                            $ir  = (float) ($get('montant_ir')  ?? 0);
                            $net = (float) ($get('net_a_payer') ?? 0);

                            // Alerte selon zone
                            $alertHtml = '';
                            if ($ttc > 0) {
                                if ($ttc < $seuilAd) {
                                    $alertHtml =
                                        '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                        . 'bg-green-100 text-green-700 '
                                        . 'dark:bg-green-900/40 dark:text-green-300">'
                                        . '✅ Achat direct valide — TTC strictement '
                                        . 'inférieur à '
                                        . number_format($seuilAd, 0, ',', ' ')
                                        . ' FCFA'
                                        . '</div>';
                                } elseif ($ttc < $seuilBcr) {
                                    $alertHtml =
                                        '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                        . 'bg-yellow-100 text-yellow-700 '
                                        . 'dark:bg-yellow-900/40 dark:text-yellow-300">'
                                        . '⚠️ Ce montant nécessite un BCR/BCM — '
                                        . 'TTC ≥ ' . number_format($seuilAd, 0, ',', ' ')
                                        . ' FCFA. Utilisez l\'onglet BCR/BCM.'
                                        . '</div>';
                                } else {
                                    $alertHtml =
                                        '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                        . 'bg-red-100 text-red-700 '
                                        . 'dark:bg-red-900/40 dark:text-red-300">'
                                        . '🚫 Dépasse le seuil BCR — procédure marché public '
                                        . 'requise (TTC ≥ '
                                        . number_format($seuilBcr, 0, ',', ' ')
                                        . ' FCFA)'
                                        . '</div>';
                                }
                            }

                            // Tableau des limites
                            $limitesHtml =
                                '<div class="rounded-lg p-3 text-sm '
                                . 'bg-blue-50 dark:bg-blue-900/30 '
                                . 'text-blue-800 dark:text-blue-200 '
                                . 'border border-blue-200 dark:border-blue-700 mb-3">'
                                . '<table class="w-full leading-loose">'
                                . '<thead><tr class="border-b border-blue-200 dark:border-blue-700 '
                                . 'text-xs font-semibold uppercase">'
                                . '<th class="text-left">Type</th>'
                                . '<th class="text-right">Seuil TTC</th>'
                                . '<th class="text-right">NAP max</th>'
                                . '</tr></thead>'
                                . '<tbody>'
                                . '<tr class="text-green-700 dark:text-green-400">'
                                . '<td>✅ Achat direct</td>'
                                . '<td class="text-right">Strictement &lt; '
                                . number_format($seuilAd, 0, ',', ' ') . ' FCFA</td>'
                                . '<td class="text-right font-bold">&lt; '
                                . number_format($napMaxAd, 0, ',', ' ') . ' FCFA</td>'
                                . '</tr>'
                                . '<tr class="text-yellow-700 dark:text-yellow-400">'
                                . '<td>📋 BCR / BCM</td>'
                                . '<td class="text-right">≥ '
                                . number_format($seuilAd, 0, ',', ' ')
                                . ' et &lt; '
                                . number_format($seuilBcr, 0, ',', ' ') . ' FCFA</td>'
                                . '<td class="text-right">—</td>'
                                . '</tr>'
                                . '<tr class="text-red-700 dark:text-red-400">'
                                . '<td>🚫 Marché public</td>'
                                . '<td class="text-right">≥ '
                                . number_format($seuilBcr, 0, ',', ' ') . ' FCFA</td>'
                                . '<td class="text-right">—</td>'
                                . '</tr>'
                                . '</tbody></table></div>';

                            // Résumé montants calculés
                            $resumeHtml = '';
                            if ($ht > 0 || $net > 0) {
                                $resumeHtml =
                                    '<div class="rounded-lg p-3 text-sm '
                                    . 'bg-slate-50 dark:bg-slate-900 '
                                    . 'text-slate-800 dark:text-slate-200 '
                                    . 'border border-slate-200 dark:border-slate-700">'
                                    . '<table class="w-full leading-loose">'
                                    . '<tr>'
                                    . '<td>Montant HT :</td>'
                                    . '<td class="text-right font-semibold">'
                                    . number_format($ht, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr>'
                                    . '<td>TVA (' . $tauxTv . '%) :</td>'
                                    . '<td class="text-right">'
                                    . number_format($tva, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr class="border-t border-slate-300 dark:border-slate-600">'
                                    . '<td class="font-semibold">Montant TTC :</td>'
                                    . '<td class="text-right font-bold">'
                                    . number_format($ttc, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr>'
                                    . '<td>IR (' . $tauxIr . '%) :</td>'
                                    . '<td class="text-right '
                                    . 'text-red-600 dark:text-red-400">'
                                    . number_format($ir, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '<tr class="border-t-2 '
                                    . 'border-green-500 dark:border-green-400">'
                                    . '<td class="font-bold '
                                    . 'text-green-700 dark:text-green-400">'
                                    . 'Net à Payer :</td>'
                                    . '<td class="text-right font-bold text-base '
                                    . 'text-green-700 dark:text-green-400">'
                                    . number_format($net, 0, ',', ' ') . ' FCFA</td>'
                                    . '</tr>'
                                    . '</table>'
                                    . $alertHtml
                                    . '</div>';
                            }

                            return new \Illuminate\Support\HtmlString(
                                $limitesHtml . $resumeHtml
                            );
                        })
                        ->columnSpanFull(),

                    // Champs cachés — valeurs calculées persistées
                    Forms\Components\Hidden::make('montant_ht')->default(0),
                    Forms\Components\Hidden::make('montant_tva')->default(0),
                    Forms\Components\Hidden::make('montant_ttc')->default(0),
                    Forms\Components\Hidden::make('montant_ir')->default(0),
                    Forms\Components\Hidden::make('net_a_payer')->default(0),
                ]),

            // ── Section 5 : Justificatif ───────────────────────
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
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ]);
    }

    // =========================================================
    // CALCUL DEPUIS NAP OU HT
    // =========================================================
    protected static function recalculer(Get $get, Set $set): void
    {
        $mode   = $get('mode_saisie_montant') ?? 'nap';
        $tauxTv = (float) ($get('taux_tva') ?? 19.25);
        $tauxIr = (float) ($get('taux_ir')  ?? 5.5);

        if ($mode === 'nap') {
            // ── Depuis NAP ────────────────────────────────────
            // MHT = NAP / (1 - IR/100)
            // TVA = MHT × TVA/100
            // TTC = MHT + TVA
            // IR  = MHT × IR/100
            // NAP = MHT - IR ✅
            $nap = (float) ($get('net_a_payer_input') ?? 0);
            if ($nap <= 0) return;

            $mht = $tauxIr > 0
                ? round($nap / (1 - $tauxIr / 100), 2)
                : $nap;
        } else {
            // ── Depuis Montant HT ─────────────────────────────
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
    public function table(Tables\Table $table): Tables\Table
    {
        $seuilAd  = static::getSeuilAchatDirect();
        $seuilBcr = static::getSeuilBonCommande();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('date_depense')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('fournisseur_affiche')
                    ->label('Fournisseur')
                    ->getStateUsing(
                        fn($record) =>
                        $record->fournisseur?->raison_sociale
                            ?? $record->fournisseur_libre
                            ?? '—'
                    )
                    ->limit(20),

                Tables\Columns\TextColumn::make('ligneRegieAvance.nomenclature.code')
                    ->label('Nomenclature')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('MHT')
                    ->money('XAF')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_tva')
                    ->label('TVA')
                    ->money('XAF')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')
                    ->money('XAF')
                    ->weight('bold')
                    ->color(function ($record) use ($seuilAd, $seuilBcr) {
                        return match (true) {
                            $record->montant_ttc < $seuilAd  => 'success',
                            $record->montant_ttc < $seuilBcr => 'warning',
                            default                          => 'danger',
                        };
                    })
                    ->tooltip(function ($record) use ($seuilAd, $seuilBcr) {
                        return match (true) {
                            $record->montant_ttc < $seuilAd  =>
                            '✅ Achat direct valide',
                            $record->montant_ttc < $seuilBcr =>
                            '⚠️ Dépasse le seuil achat direct — BCR requis',
                            default =>
                            '🚫 Dépasse le seuil BCR — marché public requis',
                        };
                    })
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TTC'),
                    ]),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')
                    ->money('XAF')
                    ->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total IR'),
                    ]),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net à Payer')
                    ->money('XAF')
                    ->color('success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total Net'),
                    ]),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('➕ Nouvel achat direct')
                    ->visible(
                        fn() =>
                        $this->getOwnerRecord()?->statut === 'actif'
                            && auth()->user()?->can('create_depense_regie')
                    )
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['type_depense'] = 'achat_direct';
                        unset(
                            $data['net_a_payer_input'],
                            $data['montant_ht_input'],
                            $data['mode_saisie_montant']
                        );
                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()?->recalculerMontants();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'brouillon'
                            && auth()->user()?->can('update_depense_regie')
                    )
                    ->mutateFormDataUsing(function (array $data): array {
                        unset(
                            $data['net_a_payer_input'],
                            $data['montant_ht_input'],
                            $data['mode_saisie_montant']
                        );
                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()?->recalculerMontants();
                    }),

                // ── Valider (débite la provision) ─────────────
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'brouillon'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Valider la dépense')
                    ->modalDescription(function ($record) use ($seuilAd, $seuilBcr) {
                        $prov    = $record?->provisionLigneRegie;
                        $warning = '';

                        if ($record?->montant_ttc >= $seuilAd) {
                            $warning = '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                . 'bg-yellow-100 text-yellow-700 '
                                . 'dark:bg-yellow-900/40 dark:text-yellow-300">'
                                . '⚠️ Attention : ce montant TTC dépasse le seuil '
                                . 'achat direct (' . number_format($seuilAd, 0, ',', ' ') . ' FCFA).'
                                . '</div>';
                        }

                        return new \Illuminate\Support\HtmlString(
                            '<div class="text-sm '
                                . 'text-slate-800 dark:text-slate-200">'
                                . '<p>Montant TTC : <strong>'
                                . number_format($record?->montant_ttc ?? 0, 0, ',', ' ')
                                . ' FCFA</strong></p>'
                                . '<p>Net à Payer : <strong class="'
                                . 'text-green-600 dark:text-green-400">'
                                . number_format($record?->net_a_payer ?? 0, 0, ',', ' ')
                                . ' FCFA</strong></p>'
                                . '<p>IR retenu : <strong class="'
                                . 'text-red-600 dark:text-red-400">'
                                . number_format($record?->montant_ir ?? 0, 0, ',', ' ')
                                . ' FCFA</strong></p>'
                                . ($prov
                                    ? '<p>Provision disponible : <strong>'
                                    . number_format($prov->montant_disponible, 0, ',', ' ')
                                    . ' FCFA</strong></p>'
                                    : ''
                                )
                                . $warning
                                . '</div>'
                        );
                    })
                    ->action(function ($record) {
                        try {
                            if ($record->provision_ligne_regie_id) {
                                ProvisionLigneRegie::findOrFail(
                                    $record->provision_ligne_regie_id
                                )->debiter($record->montant_ttc);
                            }

                            $record->update(['statut' => 'valide']);

                            Notification::make()
                                ->title('✅ Achat direct validé — provision débitée')
                                ->success()
                                ->body(
                                    "Net versé : "
                                        . number_format($record->net_a_payer, 0, ',', ' ')
                                        . " FCFA | IR retenu : "
                                        . number_format($record->montant_ir, 0, ',', ' ')
                                        . " FCFA"
                                )
                                ->send();

                            $this->getOwnerRecord()?->recalculerMontants();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur validation')
                                ->danger()
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                // ── Marquer payé ──────────────────────────────
                Tables\Actions\Action::make('payer')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'valide'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['statut' => 'paye']);
                        Notification::make()
                            ->title('✅ Dépense marquée payée')
                            ->success()
                            ->send();
                    }),

                // ── Annuler (restitue la provision) ───────────
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record?->statut, ['brouillon', 'valide'])
                            && auth()->user()?->can('annuler_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif d\'annulation')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Restituer la provision si la dépense était validée
                            if (
                                $record->statut === 'valide'
                                && $record->provision_ligne_regie_id
                            ) {
                                ProvisionLigneRegie::find(
                                    $record->provision_ligne_regie_id
                                )?->crediter($record->montant_ttc);
                            }

                            $record->update([
                                'statut'       => 'annule',
                                'observations' => ($record->observations ?? '')
                                    . "\n--- ANNULÉ "
                                    . now()->format('d/m/Y H:i')
                                    . " ---\nMotif : " . $data['motif']
                                    . "\nPar : " . auth()->user()->name,
                            ]);

                            Notification::make()
                                ->title('Achat direct annulé')
                                ->warning()
                                ->body('La provision a été restituée.')
                                ->send();

                            $this->getOwnerRecord()?->recalculerMontants();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur annulation')
                                ->danger()
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(
                            fn() =>
                            auth()->user()?->can('delete_depense_regie')
                        ),
                ]),
            ]);
    }
}
