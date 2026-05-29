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

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Dépensé')->money('XAF')->color('danger'),

                Tables\Columns\TextColumn::make('montant_ir_collecte')
                    ->label('IR collecté')->money('XAF')->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_solde')
                    ->label('Solde')->money('XAF')
                    ->color(
                        fn($record) => ($record->montant_solde ?? 0) < 0 ? 'danger' : 'success'
                    ),

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
                        return [
                            Forms\Components\TextInput::make('montant_accorde')
                                ->label('Montant accordé (FCFA)')
                                ->numeric()->required()->prefix('FCFA')
                                // ✅ Par défaut = montant demandé
                                ->default(fn() => $record->montant_demande)
                                ->helperText(
                                    'Montant demandé : '
                                        . number_format($record->montant_demande, 0, ',', ' ')
                                        . ' FCFA'
                                ),

                            Forms\Components\DatePicker::make('date_decaissement')
                                ->label('Date de versement')
                                ->default(now())->required(),

                            // ❌ certificat_numero supprimé
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $regie = $this->getOwnerRecord();

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

                        // ✅ Créer les provisions automatiquement
                        $this->creerProvisions($record, $regie, (float) $data['montant_accorde']);

                        Notification::make()
                            ->title('✅ Décaissement accordé et versé')
                            ->success()
                            ->body('Les provisions ont été créées sur les lignes concernées.')
                            ->send();
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
                        '<div class="text-sm text-slate-800 dark:text-slate-200">'
                            . "<p>Tranche : <strong>{$record->libelle_tranche}</strong></p>"
                            . "<p>Dépensé : <strong>"
                            . number_format($record->montant_depense, 0, ',', ' ')
                            . " FCFA</strong></p>"
                            . "<p>IR collecté : <strong class='text-red-600 dark:text-red-400'>"
                            . number_format($record->montant_ir_collecte, 0, ',', ' ')
                            . " FCFA</strong></p>"
                            . '</div>'
                    ))
                    ->action(function ($record) {
                        $record->recalculerDepenses();
                        $record->update([
                            'statut'         => 'apure',
                            'date_apurement' => now(),
                        ]);
                        Notification::make()->title('✅ Tranche apurée')->success()->send();
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

    // =========================================================
    // CRÉER LES PROVISIONS AUTOMATIQUEMENT
    // =========================================================
    protected function creerProvisions(
        DecaissementRegie $decaissement,
        \App\Models\RegieAvance $regie,
        float $montantAccorde
    ): void {
        $lignes = $regie->lignes()->with('nomenclature')->get();

        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('⚠️ Aucune ligne budgétaire')
                ->warning()
                ->body('Ajoutez des lignes de nomenclature à la régie avant de décaisser.')
                ->send();
            return;
        }

        // ── RAV : 1 seule ligne → provision automatique totale ──
        if ($regie->type === 'rav' || $lignes->count() === 1) {
            $ligne = $lignes->first();

            // Vérifier si provision déjà existante
            $existante = ProvisionLigneRegie::where([
                'decaissement_regie_id' => $decaissement->id,
                'ligne_regie_avance_id' => $ligne->id,
            ])->first();

            if (!$existante) {
                ProvisionLigneRegie::create([
                    'decaissement_regie_id' => $decaissement->id,
                    'ligne_regie_avance_id' => $ligne->id,
                    'montant_provisionne'   => $montantAccorde,
                    'montant_consomme'      => 0,
                    'montant_disponible'    => $montantAccorde,
                ]);
            }

            return;
        }

        // ── Menu Dépense : N lignes → répartition proportionnelle ──
        $totalAlloue = $lignes->sum('montant_alloue');

        if ($totalAlloue <= 0) {
            // Si pas de montants alloués, répartition égale
            $montantParLigne = round($montantAccorde / $lignes->count(), 2);
            foreach ($lignes as $index => $ligne) {
                // Dernière ligne prend l'arrondi
                $montant = ($index === $lignes->count() - 1)
                    ? $montantAccorde - ($montantParLigne * ($lignes->count() - 1))
                    : $montantParLigne;

                $this->creerOuMettreAJourProvision($decaissement, $ligne, $montant);
            }
            return;
        }

        // Répartition proportionnelle au montant alloué de chaque ligne
        $totalCree  = 0;
        $lignesArray = $lignes->values();

        foreach ($lignesArray as $index => $ligne) {
            $proportion = (float) $ligne->montant_alloue / $totalAlloue;

            // Dernière ligne = reste pour éviter les erreurs d'arrondi
            if ($index === $lignesArray->count() - 1) {
                $montant = round($montantAccorde - $totalCree, 2);
            } else {
                $montant = round($montantAccorde * $proportion, 2);
            }

            $totalCree += $montant;
            $this->creerOuMettreAJourProvision($decaissement, $ligne, $montant);
        }
    }

    protected function creerOuMettreAJourProvision(
        DecaissementRegie $decaissement,
        LigneRegieAvance $ligne,
        float $montant
    ): void {
        $existante = ProvisionLigneRegie::where([
            'decaissement_regie_id' => $decaissement->id,
            'ligne_regie_avance_id' => $ligne->id,
        ])->first();

        if ($existante) {
            $existante->updateQuietly([
                'montant_provisionne' => $montant,
                'montant_disponible'  => $montant - $existante->montant_consomme,
            ]);
        } else {
            ProvisionLigneRegie::create([
                'decaissement_regie_id' => $decaissement->id,
                'ligne_regie_avance_id' => $ligne->id,
                'montant_provisionne'   => $montant,
                'montant_consomme'      => 0,
                'montant_disponible'    => $montant,
            ]);
        }
    }
}
