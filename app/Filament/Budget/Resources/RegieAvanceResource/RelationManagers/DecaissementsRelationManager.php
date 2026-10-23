<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use App\Models\ProvisionLigneRegie;
use App\Models\DecaissementRegie;
use App\Models\LigneRegieAvance;

class DecaissementsRelationManager extends RelationManager
{
    protected static string $relationship = 'decaissements';
    protected static ?string $title       = 'Décaissements (Tranches)';

    public function form(Forms\Form $form): Forms\Form
    {
        $regie = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                Forms\Components\TextInput::make('numero')
                    ->label('N° Tranche')
                    ->disabled()->dehydrated()
                    ->placeholder('Généré automatiquement'),

                Forms\Components\TextInput::make('libelle_tranche')
                    ->label('Libellé tranche')
                    ->placeholder('Ex: Tranche T1 2025')
                    ->required(),

                Forms\Components\Select::make('trimestre')
                    ->label('Trimestre indicatif')
                    ->options([
                        1 => 'T1 (Jan-Mar)',
                        2 => 'T2 (Avr-Jun)',
                        3 => 'T3 (Jul-Sep)',
                        4 => 'T4 (Oct-Déc)',
                    ])
                    ->default(ceil(now()->month / 3))
                    ->required(),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('montant_demande')
                    ->label('Montant demandé (FCFA)')
                    ->numeric()->required()->prefix('FCFA')
                    ->helperText(function () use ($regie) {
                        return 'Disponible régie : '
                            . number_format($regie->montant_disponible, 0, ',', ' ')
                            . ' FCFA';
                    }),

                Forms\Components\DatePicker::make('date_demande')
                    ->label('Date de demande')
                    ->default(now())->required(),
            ]),

            Forms\Components\Textarea::make('observations')
                ->label('Observations')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Tranche')->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('libelle_tranche')
                    ->label('Libellé'),

                Tables\Columns\TextColumn::make('trimestre')
                    ->label('Trimestre')
                    ->formatStateUsing(fn($state) => "T{$state}")
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('montant_demande')
                    ->label('Demandé')->money('XAF'),

                Tables\Columns\TextColumn::make('montant_accorde')
                    ->label('Accordé')->money('XAF')->color('success'),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Engagé')
                    ->money('XAF')
                    ->color('danger')
                    ->tooltip(fn($record) =>
                        'Consommation de l\'encaisse dès l\'engagement des BCR et la '
                        . 'validation des achats directs — avant livraison et paiement.'),

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Payé / livré')->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('montant_ir_collecte')
                    ->label('IR collecté')->money('XAF')->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_solde')
                    ->label('Reste employable')->money('XAF')
                    ->color(
                        fn($record) => ($record->montant_solde ?? 0) < 0 ? 'danger' : 'success'
                    )
                    ->tooltip(fn($record) =>
                        'Accordé ' . number_format((float) $record->montant_accorde, 0, ',', ' ')
                        . ' − engagé ' . number_format((float) $record->montant_engage, 0, ',', ' ') . ' FCFA'),

                Tables\Columns\TextColumn::make('date_decaissement')
                    ->label('Date versement')->date('d/m/Y'),

                Tables\Columns\TextColumn::make('provisions_count')
                    ->label('Lignes provisionnées')
                    ->counts('provisions')
                    ->badge()->color('gray'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'gray'    => 'demande',
                        'warning' => 'accorde',
                        'success' => 'verse',
                        'primary' => 'apure',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'demande' => 'Demandé',
                        'accorde' => 'Accordé',
                        'verse'   => 'Versé',
                        'apure'   => 'Apuré',
                        default   => $state,
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouvelle demande de décaissement')
                    ->visible(fn() => $this->getOwnerRecord()?->statut === 'actif'),
            ])
            ->actions([

                // ── Étape 1 : Accorder & Verser ───────────────
                Tables\Actions\Action::make('accorder')
                    ->label('Accorder & Verser')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'demande'
                            && auth()->user()?->can('valider_decaissement_regie')
                    )
                    ->modalHeading('Accorder et verser la tranche')
                    ->form(function ($record) {  // ✅ closure pour accéder à $record
                        $regie        = $this->getOwnerRecord();
                        $enveloppe    = (float) ($regie->montant_alloue ?? 0);
                        $dejaEncaisse = (float) $regie->decaissements()
                            ->where('id', '!=', $record->id)
                            ->whereIn('statut', ['verse', 'apure'])
                            ->sum('montant_accorde');
                        $plafondTranche = max(0, $enveloppe - $dejaEncaisse);

                        return [
                            Forms\Components\TextInput::make('montant_accorde')
                                ->label('Montant accordé (FCFA)')
                                ->numeric()->required()->prefix('FCFA')
                                // ✅ Par défaut = montant demandé
                                ->default(fn() => $record->montant_demande)
                                ->helperText(
                                    'Montant demandé : '
                                        . number_format($record->montant_demande, 0, ',', ' ')
                                        . ' FCFA — plafond de la régie (DA sources) : '
                                        . number_format($plafondTranche, 0, ',', ' ') . ' FCFA'
                                )
                                ->maxValue($enveloppe > 0 ? $plafondTranche : null),

                            Forms\Components\DatePicker::make('date_decaissement')
                                ->label('Date de versement')
                                ->default(now())->required(),

                            // ❌ certificat_numero supprimé
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $regie     = $this->getOwnerRecord();
                        $enveloppe = (float) ($regie->montant_alloue ?? 0);
                        $accorde   = (float) $data['montant_accorde'];

                        $dejaEncaisse = (float) $regie->decaissements()
                            ->where('id', '!=', $record->id)
                            ->whereIn('statut', ['verse', 'apure'])
                            ->sum('montant_accorde');

                        if ($enveloppe > 0 && $accorde > $enveloppe - $dejaEncaisse + 0.01) {
                            throw new \Exception(
                                "Plafond de la régie dépassé — cette tranche ne peut pas être versée.\n"
                                    . 'Enveloppe des DA : ' . number_format($enveloppe, 0, ',', ' ') . " FCFA\n"
                                    . 'Déjà encaissé par les autres tranches : '
                                    . number_format($dejaEncaisse, 0, ',', ' ') . " FCFA\n"
                                    . 'Maximum pour cette tranche : '
                                    . number_format(max(0, $enveloppe - $dejaEncaisse), 0, ',', ' ') . " FCFA\n"
                                    . 'Montant accordé demandé : ' . number_format($accorde, 0, ',', ' ')
                                    . " FCFA\nSolution : alimenter l'enveloppe de la régie par une nouvelle DA "
                                    . 'avant de verser cette tranche.'
                            );
                        }

                        $record->update([
                            'statut'            => 'verse',
                            'montant_accorde'   => $data['montant_accorde'],
                            'date_decaissement' => $data['date_decaissement'],
                            // ❌ certificat_numero supprimé
                        ]);

                        // Mettre à jour montant_decaisse de la régie
                        $regie->updateQuietly([
                            'montant_decaisse' => $regie->decaissements()
                                ->whereIn('statut', ['verse', 'apure'])
                                ->sum('montant_accorde'),
                        ]);

                        try {
                            // ✅ La tranche crée SA propre ligne de mini-budget puis la
                            //    provisionne — jamais de cumul sur la ligne d'un autre encaisse.
                            \DB::transaction(
                                fn() => $record->creerProvisions($regie, (float) $data['montant_accorde'])
                            );

                            Notification::make()
                                ->title('✅ Décaissement accordé et versé')
                                ->success()
                                ->body('Une ligne de mini-budget propre à cette tranche a été créée et provisionnée.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Tranche versée mais non provisionnée')
                                ->danger()
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                // ── Voir les provisions ────────────────────────
                Tables\Actions\Action::make('voir_provisions')
                    ->label('Voir provisions')
                    ->icon('heroicon-o-eye')->color('info')
                    ->visible(
                        fn($record) => ($record?->provisions()->count() ?? 0) > 0
                    )
                    ->modalHeading(fn($record) => "Provisions — {$record->libelle_tranche}")
                    ->modalContent(function ($record) {
                        $provisions = $record->provisions()
                            ->with('ligneRegie.nomenclature')
                            ->get();

                        $rows = $provisions->map(
                            fn($p) =>
                            '<tr class="border-b border-gray-200 dark:border-gray-700">'
                                . '<td class="px-3 py-2">'
                                . '<span class="px-2 py-0.5 rounded text-xs '
                                . 'bg-gray-100 dark:bg-gray-700 '
                                . 'text-gray-700 dark:text-gray-300">'
                                . $p->ligneRegie->nomenclature->code . '</span>'
                                . ' ' . $p->ligneRegie->nomenclature->libelle
                                . '</td>'
                                . '<td class="px-3 py-2 text-right">'
                                . number_format($p->montant_provisionne, 0, ',', ' ') . ' FCFA'
                                . '</td>'
                                . '<td class="px-3 py-2 text-right '
                                . 'text-red-600 dark:text-red-400">'
                                . number_format($p->montant_consomme, 0, ',', ' ') . ' FCFA'
                                . '</td>'
                                . '<td class="px-3 py-2 text-right font-bold '
                                . 'text-green-600 dark:text-green-400">'
                                . number_format($p->montant_disponible, 0, ',', ' ') . ' FCFA'
                                . '</td>'
                                . '</tr>'
                        )->implode('');

                        return new \Illuminate\Support\HtmlString(
                            '<table class="w-full text-sm '
                                . 'text-slate-800 dark:text-slate-200">'
                                . '<thead>'
                                . '<tr class="bg-slate-100 dark:bg-slate-800 text-xs uppercase">'
                                . '<th class="px-3 py-2 text-left">Nomenclature</th>'
                                . '<th class="px-3 py-2 text-right">Provisionné</th>'
                                . '<th class="px-3 py-2 text-right">Consommé</th>'
                                . '<th class="px-3 py-2 text-right">Disponible</th>'
                                . '</tr></thead>'
                                . '<tbody>' . $rows . '</tbody>'
                                . '</table>'
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),

                // ── Ajuster les provisions (MD uniquement) ─────
                Tables\Actions\Action::make('ajuster_provisions')
                    ->label('Ajuster provisions')
                    ->icon('heroicon-o-adjustments-horizontal')->color('warning')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'verse'
                            && $this->getOwnerRecord()?->type === 'menu_depense'
                            && ($record->provisions()->count() ?? 0) > 0
                            && auth()->user()?->can('valider_decaissement_regie')
                    )
                    ->modalHeading('Ajuster la répartition sur les lignes')
                    ->modalDescription(
                        fn($record) =>
                        'Montant accordé : '
                            . number_format($record->montant_accorde, 0, ',', ' ')
                            . ' FCFA'
                    )
                    ->form(function ($record) {
                        $regie      = $this->getOwnerRecord();
                        $provisions = $record->provisions()
                            ->with('ligneRegie.nomenclature')
                            ->get();
                        $schema = [];

                        foreach ($provisions as $prov) {
                            $schema[] = Forms\Components\TextInput::make(
                                "provision_{$prov->id}"
                            )
                                ->label(
                                    "{$prov->ligneRegie->nomenclature->code} — "
                                        . "{$prov->ligneRegie->nomenclature->libelle}"
                                )
                                ->numeric()
                                ->default($prov->montant_provisionne)
                                ->prefix('FCFA')
                                ->helperText(
                                    'Consommé : '
                                        . number_format($prov->montant_consomme, 0, ',', ' ')
                                        . ' FCFA | Minimum = montant déjà consommé'
                                );
                        }

                        $schema[] = Forms\Components\Placeholder::make('total_info')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="rounded p-2 text-xs '
                                    . 'bg-blue-50 dark:bg-blue-900/30 '
                                    . 'text-blue-700 dark:text-blue-300">'
                                    . '💡 Le total doit être égal au montant accordé : '
                                    . '<strong>'
                                    . number_format($record->montant_accorde, 0, ',', ' ')
                                    . ' FCFA</strong>'
                                    . '</div>'
                            ))
                            ->columnSpanFull();

                        return $schema;
                    })
                    ->action(function ($record, array $data) {
                        $provisions = $record->provisions()
                            ->with('ligneRegie.nomenclature')
                            ->get();

                        foreach ($provisions as $prov) {
                            $nouveau = (float) ($data["provision_{$prov->id}"] ?? $prov->montant_provisionne);

                            // Ne pas descendre en dessous du consommé
                            $nouveau = max($nouveau, (float) $prov->montant_consomme);

                            $prov->updateQuietly([
                                'montant_provisionne' => $nouveau,
                                'montant_disponible'  => $nouveau - $prov->montant_consomme,
                            ]);
                        }

                        Notification::make()
                            ->title('✅ Provisions ajustées')
                            ->success()->send();
                    }),

                // ── Apurer ────────────────────────────────────
                Tables\Actions\Action::make('apurer')
                    ->label('Apurer')
                    ->icon('heroicon-o-clipboard-document-check')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record?->statut === 'verse'
                            && ($record->provisions()->count() ?? 0) > 0
                            && auth()->user()?->can('apurer_decaissement_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Apurer la tranche')
                    ->modalDescription(fn($record) => new \Illuminate\Support\HtmlString(
                        (function () use ($record) {
                            // ✅ Utilise la méthode centralisée (cascade-aware, basée sur
                            //    provision_consommations) au lieu de resommer les BCR
                            //    par leur "provision principale" — évite toute divergence
                            //    avec ce qui est réellement enregistré.
                            $record->load('provisions');
                            $record->recalculerDepenses();
                            $record->refresh();

                            $totalDepense = (float) $record->montant_depense;
                            $totalEngage  = (float) $record->montant_engage;
                            $totalIr      = (float) $record->montant_ir_collecte;
                            $solde        = (float) $record->montant_solde;

                            $couleurSolde = $solde < 0 ? 'color:#dc2626;' : 'color:#16a34a;';

                            return '<div class="text-sm text-slate-800 dark:text-slate-200" style="line-height:2;">'
                                . "<p>Tranche : <strong>{$record->libelle_tranche}</strong></p>"
                                . "<p>Montant accordé : <strong>"
                                . number_format($record->montant_accorde, 0, ',', ' ') . " FCFA</strong></p>"
                                . "<p>Engagé (déjà consommé de l'encaisse) : <strong style='color:#b91c1c;'>"
                                . number_format($totalEngage, 0, ',', ' ') . " FCFA</strong></p>"
                                . "<p>Livré / payé : <strong style='color:#1d4ed8;'>"
                                . number_format($totalDepense, 0, ',', ' ') . " FCFA</strong></p>"
                                . "<p>IR collecté : <strong style='color:#dc2626;'>"
                                . number_format($totalIr, 0, ',', ' ') . " FCFA</strong></p>"
                                . "<p>Reste employable : <strong style='{$couleurSolde}'>"
                                . number_format($solde, 0, ',', ' ') . " FCFA</strong></p>"
                                . '<hr style="margin:8px 0;border-color:#e2e8f0;">'
                                . '<p style="font-size:.78rem;color:#64748b;">✅ L\'encaisse est consommé dès '
                                . 'l\'engagement du BCR : ce qui est engagé n\'est plus dépensable, même non '
                                . 'livré ni payé. L\'apurement comptabilise l\'IR sur le livré/payé.</p>'
                                . '</div>';
                        })()
                    ))
                    ->action(function ($record) {
                        // ✅ Recalculer et enregistrer
                        $record->load('provisions');
                        $record->recalculerDepenses();

                        // ✅ Recharger après recalcul pour avoir les bonnes valeurs
                        $record->refresh();

                        $record->update([
                            'statut'         => 'apure',
                            'date_apurement' => now(),
                        ]);

                        Notification::make()
                            ->title('✅ Tranche apurée')
                            ->success()
                            ->body(
                                "Dépensé : " . number_format($record->montant_depense, 0, ',', ' ') . " FCFA\n"
                                    . "IR collecté : " . number_format($record->montant_ir_collecte, 0, ',', ' ') . " FCFA"
                            )
                            ->send();
                    }),

                // ── Pièces comptables de la tranche ───────────────
                // L'édition de la tranche n'est possible qu'à l'état « demande » :
                // ces références, elles, se renseignent après versement et même
                // après apurement, quand le mandat et la quittance existent.
                Tables\Actions\Action::make('pieces')
                    ->label('Pièces')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->modalHeading(fn($record) => 'Pièces de l\'encaisse — ' . ($record->libelle_tranche ?: $record->numero))
                    ->modalDescription('Numéros et dates repris tels quels dans le texte qui précède le compte d\'emploi.')
                    ->fillForm(fn($record) => [
                        'certificat_numero' => $record->certificat_numero,
                        'certificat_date'   => $record->certificat_date,
                        'mandat_numero'     => $record->mandat_numero,
                        'mandat_date'       => $record->mandat_date,
                        'quittance_numero'  => $record->quittance_numero,
                        'quittance_date'    => $record->quittance_date,
                    ])
                    ->form([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('certificat_numero')
                                ->label("Certificat d'engagement — n°")
                                ->maxLength(120)
                                ->columnSpan(2),

                            Forms\Components\DatePicker::make('certificat_date')
                                ->label('du'),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('mandat_numero')
                                ->label('Mandat de décaissement — n°')
                                ->maxLength(120)
                                ->placeholder('Ex: CFS/073')
                                ->columnSpan(2),

                            Forms\Components\DatePicker::make('mandat_date')
                                ->label('du'),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('quittance_numero')
                                ->label('Quittance de reversement du solde — n°')
                                ->maxLength(120)
                                ->columnSpan(2),

                            Forms\Components\DatePicker::make('quittance_date')
                                ->label('du'),
                        ]),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update($data);

                        Notification::make()
                            ->title('✅ Pièces de l\'encaisse enregistrées')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record?->statut === 'demande'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('apercu_mandat')
                        ->label('Aperçu Mandat')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn($record) => $record->statut === 'verse')
                        ->action(function ($record, $livewire) {
                            $livewire->dispatch('open-url-new-tab', url: route(
                                'mandat.decaissement.apercu',
                                [
                                    'regie'         => $record->regie_avance_id,
                                    'decaissement'  => $record->id, // ✅ DecaissementRegie id
                                ]
                            ));
                        }),

                    Tables\Actions\Action::make('telecharger_mandat')
                        ->label('Télécharger Mandat')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn($record) => $record->statut === 'verse')
                        ->action(function ($record, $livewire) {
                            $livewire->dispatch('open-url-new-tab', url: route(
                                'mandat.decaissement.telecharger',
                                [
                                    'regie'        => $record->regie_avance_id,
                                    'decaissement' => $record->id,
                                ]
                            ));
                        }),
                ])
                    ->label('📄 Mandat')
                    ->icon('heroicon-o-document-text')
                    ->size('sm')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
