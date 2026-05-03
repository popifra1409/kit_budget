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

class DepensesRelationManager extends RelationManager
{
    protected static string $relationship = 'depenses';
    protected static ?string $title       = 'Achats Directs';

    // ✅ Utilitaire réutilisable — ajouter en haut de la classe
    protected static function infoBox(string $html): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div class="rounded-lg p-3 text-sm leading-relaxed '
                . 'bg-gray-100 text-gray-800 '
                . 'dark:bg-gray-800 dark:text-gray-200">'
                . $html
                . '</div>'
        );
    }

    protected static function dangerBox(string $html): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div class="rounded-lg p-3 text-sm font-semibold '
                . 'bg-red-50 text-red-700 border border-red-300 '
                . 'dark:bg-red-900/30 dark:text-red-300 dark:border-red-700">'
                . $html
                . '</div>'
        );
    }

    protected static function successBox(string $html): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div class="rounded-lg p-3 text-sm '
                . 'bg-green-50 text-green-800 border border-green-200 '
                . 'dark:bg-green-900/30 dark:text-green-300 dark:border-green-700">'
                . $html
                . '</div>'
        );
    }

    public function form(Forms\Form $form): Forms\Form
    {
        $regie = $this->getOwnerRecord();
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

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

                        // Type forcé à achat_direct
                        Forms\Components\Hidden::make('type_depense')
                            ->default('achat_direct'),
                    ]),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet de la dépense')
                        ->required()->maxLength(255)->columnSpanFull(),
                ]),

            // ── Section 2 : Provision (ligne dérivée) ─────────
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

                    // Aperçu provision
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
                                '<div style="background:#f1f5f9;padding:.75rem 1rem;'
                                    . 'border-radius:.5rem;font-size:.82rem;line-height:1.8;">'
                                    . "<strong>Nomenclature :</strong> "
                                    . "{$prov->ligneRegie->nomenclature->code} — "
                                    . "{$prov->ligneRegie->nomenclature->libelle}<br>"
                                    . "<strong>Tranche :</strong> {$prov->decaissement->libelle_tranche}<br>"
                                    . "<strong>Provisionné :</strong> "
                                    . number_format($prov->montant_provisionne, 0, ',', ' ') . " FCFA<br>"
                                    . "<strong>Consommé :</strong> "
                                    . number_format($prov->montant_consomme, 0, ',', ' ') . " FCFA<br>"
                                    . "<strong style='color:green;'>Disponible :</strong> "
                                    . number_format($prov->montant_disponible, 0, ',', ' ') . " FCFA"
                                    . '</div>'
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

            // ── Section 4 : Montants (logique NAP → MHT) ──────
            Forms\Components\Section::make('Montants')
                ->description('Saisissez le Net à Payer — les autres montants sont calculés automatiquement.')
                ->schema([

                    // ── Mode de saisie ─────────────────────────
                    Forms\Components\ToggleButtons::make('mode_saisie_montant')
                        ->label('Mode de saisie')
                        ->options([
                            'nap'   => '📊 Net à Payer (NAP)',
                            'brut'  => '💰 Montant Brut (HT)',
                        ])
                        ->default('nap')
                        ->inline()
                        ->live()
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    // ── Taux TVA et IR ─────────────────────────
                    Forms\Components\Grid::make(4)->schema([

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

                        Forms\Components\Placeholder::make('seuil_info')
                            ->label('Seuil achat direct')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<span style="font-size:.8rem;color:#6b7280;">Seuil : '
                                    . number_format($seuil, 0, ',', ' ')
                                    . ' FCFA TTC</span>'
                            )),

                        Forms\Components\Placeholder::make('type_detecte')
                            ->label('Type détecté')
                            ->content(function (Get $get) use ($seuil) {
                                $ttc = (float) ($get('montant_ttc') ?? 0);
                                if ($ttc <= 0) return '—';
                                return $ttc <= $seuil
                                    ? new \Illuminate\Support\HtmlString(
                                        '<span style="color:green;font-weight:600;">✅ Achat direct</span>'
                                    )
                                    : new \Illuminate\Support\HtmlString(
                                        '<span style="color:#dc2626;font-weight:600;">'
                                            . '⚠️ Dépasse le seuil → utilisez un BCR'
                                            . '</span>'
                                    );
                            }),
                    ]),

                    // ── Saisie NAP ─────────────────────────────
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\TextInput::make('net_a_payer_input')
                            ->label('Net à Payer (NAP) — FCFA')
                            ->numeric()->prefix('FCFA')
                            ->required(fn(Get $get) => $get('mode_saisie_montant') === 'nap')
                            ->hidden(fn(Get $get) => $get('mode_saisie_montant') !== 'nap')
                            ->live(onBlur: true)
                            ->dehydrated(false)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            )
                            ->helperText('Saisir le montant net que percevra le fournisseur'),

                        Forms\Components\TextInput::make('montant_ht')
                            ->label('Montant HT — FCFA')
                            ->numeric()->prefix('FCFA')
                            ->required(fn(Get $get) => $get('mode_saisie_montant') === 'brut')
                            ->hidden(fn(Get $get) => $get('mode_saisie_montant') !== 'brut')
                            ->live(onBlur: true)
                            ->dehydrated(true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            )
                            ->helperText('Montant hors taxes'),

                    ]),

                    // ── Résumé calculé ─────────────────────────
                    Forms\Components\Placeholder::make('resume_calcul')
                        ->label('📊 Détail des montants calculés')
                        ->content(function (Get $get) {
                            $ht  = (float) ($get('montant_ht')  ?? 0);
                            $tva = (float) ($get('montant_tva') ?? 0);
                            $ttc = (float) ($get('montant_ttc') ?? 0);
                            $ir  = (float) ($get('montant_ir')  ?? 0);
                            $net = (float) ($get('net_a_payer') ?? 0);

                            if ($ht <= 0 && $net <= 0) {
                                return '← Saisissez un montant pour voir le détail';
                            }

                            return new \Illuminate\Support\HtmlString(
                                '<div style="background:#f1f5f9;padding:.75rem 1rem;'
                                    . 'border-radius:.5rem;font-size:.85rem;line-height:2;">'
                                    . '<table style="width:100%;">'
                                    . "<tr><td>Montant HT :</td><td style='text-align:right;'><strong>"
                                    . number_format($ht, 0, ',', ' ') . " FCFA</strong></td></tr>"
                                    . "<tr><td>TVA ({$get('taux_tva')}%) :</td>"
                                    . "<td style='text-align:right;'>"
                                    . number_format($tva, 0, ',', ' ') . " FCFA</td></tr>"
                                    . "<tr style='border-top:1px solid #cbd5e1;'>"
                                    . "<td><strong>Montant TTC :</strong></td>"
                                    . "<td style='text-align:right;'><strong>"
                                    . number_format($ttc, 0, ',', ' ') . " FCFA</strong></td></tr>"
                                    . "<tr><td>IR ({$get('taux_ir')}%) :</td>"
                                    . "<td style='text-align:right;color:#dc2626;'>"
                                    . number_format($ir, 0, ',', ' ') . " FCFA</td></tr>"
                                    . "<tr style='border-top:2px solid #16a34a;'>"
                                    . "<td><strong style='color:#16a34a;'>Net à Payer :</strong></td>"
                                    . "<td style='text-align:right;font-weight:bold;color:#16a34a;font-size:1rem;'>"
                                    . number_format($net, 0, ',', ' ') . " FCFA</td></tr>"
                                    . '</table></div>'
                            );
                        })
                        ->columnSpanFull(),

                    // Champs cachés — valeurs calculées
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
                ->columns(2)
                ->collapsible()->collapsed(),
        ]);
    }

    // =========================================================
    // CALCUL DEPUIS NAP ou BRUT
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

            $tva = round($mht * ($tauxTv / 100), 2);
            $ttc = round($mht + $tva, 2);
            $ir  = round($mht * ($tauxIr / 100), 2);
            $netVerif = round($mht - $ir, 2);

            $set('montant_ht',  $mht);
            $set('montant_tva', $tva);
            $set('montant_ttc', $ttc);
            $set('montant_ir',  $ir);
            $set('net_a_payer', $netVerif);
        } else {
            // ── Depuis Montant HT ─────────────────────────────
            $ht = (float) ($get('montant_ht') ?? 0);
            if ($ht <= 0) return;

            $tva = round($ht * ($tauxTv / 100), 2);
            $ttc = round($ht + $tva, 2);
            $ir  = round($ht * ($tauxIr / 100), 2);
            $net = round($ht - $ir, 2);

            $set('montant_ht',  $ht);
            $set('montant_tva', $tva);
            $set('montant_ttc', $ttc);
            $set('montant_ir',  $ir);
            $set('net_a_payer', $net);
        }
    }

    // =========================================================
    // TABLEAU
    // =========================================================
    public function table(Tables\Table $table): Tables\Table
    {
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->weight('bold')->copyable()->searchable(),

                Tables\Columns\TextColumn::make('date_depense')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(35)
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
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
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('MHT')->money('XAF')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')->money('XAF')->weight('bold')
                    ->color(
                        fn($record) =>
                        $record->montant_ttc > $seuil ? 'danger' : 'success'
                    )
                    ->tooltip(
                        fn($record) =>
                        $record->montant_ttc > $seuil
                            ? '⚠️ Dépasse le seuil achat direct'
                            : '✅ Dans la limite achat direct'
                    )
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total TTC'),
                    ]),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total IR'),
                    ]),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net à Payer')->money('XAF')->color('success')
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total Net'),
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('➕ Nouvel achat direct')
                    ->visible(fn() => $this->getOwnerRecord()?->statut === 'actif')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Forcer type_depense = achat_direct
                        $data['type_depense'] = 'achat_direct';
                        // Nettoyer le champ de saisie NAP (non persisté)
                        unset($data['net_a_payer_input'], $data['mode_saisie_montant']);
                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()?->recalculerMontants();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record?->statut === 'brouillon')
                    ->mutateFormDataUsing(function (array $data): array {
                        unset($data['net_a_payer_input'], $data['mode_saisie_montant']);
                        return $data;
                    }),

                // ── Valider (débite la provision) ─────────────
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'brouillon'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Valider la dépense')
                    ->modalDescription(function ($record) {
                        $prov = $record->provisionLigneRegie;
                        return new \Illuminate\Support\HtmlString(
                            "<p>Montant TTC : <strong>"
                                . number_format($record->montant_ttc, 0, ',', ' ')
                                . " FCFA</strong></p>"
                                . "<p>Net à Payer : <strong style='color:green;'>"
                                . number_format($record->net_a_payer, 0, ',', ' ')
                                . " FCFA</strong></p>"
                                . ($prov
                                    ? "<p>Provision disponible avant débit : <strong>"
                                    . number_format($prov->montant_disponible, 0, ',', ' ')
                                    . " FCFA</strong></p>"
                                    : ""
                                )
                        );
                    })
                    ->action(function ($record) {
                        try {
                            // Débiter la provision
                            if ($record->provision_ligne_regie_id) {
                                $provision = ProvisionLigneRegie::findOrFail(
                                    $record->provision_ligne_regie_id
                                );
                                $provision->debiter($record->montant_ttc);
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
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur validation')
                                ->danger()->body($e->getMessage())->persistent()->send();
                        }
                    }),

                // ── Marquer payé ──────────────────────────────
                Tables\Actions\Action::make('payer')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'valide'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'paye'])),

                // ── Annuler (restitue la provision) ───────────
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record?->statut, ['brouillon', 'valide'])
                            && auth()->user()?->can('annuler_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif d\'annulation')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        // Restituer la provision si déjà validé
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
                        Notification::make()->title('Achat direct annulé')->warning()->send();
                    }),
            ])
            ->defaultSort('date_depense', 'desc');
    }
}
