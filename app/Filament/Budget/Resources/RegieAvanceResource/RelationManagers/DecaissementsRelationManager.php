<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use App\Models\ProvisionLigneRegie;

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

            Forms\Components\Grid::make(3)->schema([
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

                Forms\Components\TextInput::make('certificat_numero')
                    ->label('N° Certificat de décaissement')
                    ->placeholder('Ex: CERT-2025-001'),
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
                    ->label('IR collecté')->money('XAF')->color('warning'),
                Tables\Columns\TextColumn::make('date_decaissement')
                    ->label('Date versement')->date('d/m/Y'),
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
                    ->label('Nouvelle tranche')
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'actif')
                    ->after(function () {
                        // Mettre à jour montant_decaisse
                        $regie = $this->getOwnerRecord();
                        $regie->updateQuietly([
                            'montant_decaisse' => $regie->decaissements()
                                ->whereIn('statut', ['accorde', 'verse', 'apure'])
                                ->sum('montant_accorde'),
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('repartir')
                    ->label('Répartir sur lignes')
                    ->icon('heroicon-o-arrows-pointing-out')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'verse'
                            && $record->provisions()->count() === 0
                            && auth()->user()?->can('valider_decaissement_regie')
                    )
                    ->modalHeading('Répartir le décaissement sur les lignes de nomenclature')
                    ->modalDescription(
                        fn($record) =>
                        "Répartir " . number_format($record->montant_accorde, 0, ',', ' ')
                            . " FCFA sur les lignes de nomenclature de la régie."
                    )
                    ->form(function ($record) {
                        $regie  = $record->regieAvance;
                        $lignes = $regie->lignes()->with('nomenclature')->get();
                        $schema = [];

                        foreach ($lignes as $ligne) {
                            $schema[] = Forms\Components\TextInput::make("montant_ligne_{$ligne->id}")
                                ->label(
                                    "{$ligne->nomenclature->code} — {$ligne->nomenclature->libelle} "
                                        . "(Alloué: " . number_format($ligne->montant_alloue, 0, ',', ' ') . " FCFA)"
                                )
                                ->numeric()->default(0)->prefix('FCFA')
                                ->helperText(
                                    'Disponible sur la ligne : '
                                        . number_format($ligne->montant_disponible, 0, ',', ' ') . ' FCFA'
                                );
                        }

                        // Placeholder total
                        $schema[] = Forms\Components\Placeholder::make('total_reparti')
                            ->label('⚠️ Le total doit correspondre au montant accordé')
                            ->content(
                                fn() =>
                                "Montant accordé : "
                                    . number_format($record->montant_accorde, 0, ',', ' ') . " FCFA"
                            );

                        return $schema;
                    })
                    ->action(function ($record, array $data) {
                        $regie  = $record->regieAvance;
                        $lignes = $regie->lignes()->with('nomenclature')->get();
                        $total  = 0;

                        foreach ($lignes as $ligne) {
                            $montant = (float) ($data["montant_ligne_{$ligne->id}"] ?? 0);
                            if ($montant <= 0) continue;
                            $total += $montant;

                            // Créer la provision
                            ProvisionLigneRegie::create([
                                'decaissement_regie_id'  => $record->id,
                                'ligne_regie_avance_id'  => $ligne->id,
                                'montant_provisionne'    => $montant,
                                'montant_consomme'       => 0,
                                'montant_disponible'     => $montant,
                            ]);
                        }

                        // Vérification cohérence
                        $ecart = abs($total - $record->montant_accorde);
                        if ($ecart > 1) {
                            Notification::make()
                                ->title('⚠️ Écart de répartition')
                                ->warning()
                                ->body(
                                    "Total réparti : " . number_format($total, 0, ',', ' ')
                                        . " FCFA / Accordé : "
                                        . number_format($record->montant_accorde, 0, ',', ' ') . " FCFA"
                                        . "\nÉcart : " . number_format($ecart, 0, ',', ' ') . " FCFA"
                                )
                                ->send();
                        } else {
                            Notification::make()
                                ->title('✅ Répartition effectuée')
                                ->success()
                                ->body("Les provisions ont été créées sur " . $lignes->count() . " ligne(s).")
                                ->send();
                        }
                    }),
                // ── Accorder ──────────────────────────────────
                Tables\Actions\Action::make('accorder')
                    ->label('Accorder')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'demande'
                            && auth()->user()?->can('valider_decaissement_regie')
                    )
                    ->form([
                        Forms\Components\TextInput::make('montant_accorde')
                            ->label('Montant accordé (FCFA)')
                            ->numeric()->required()->prefix('FCFA'),
                        Forms\Components\DatePicker::make('date_decaissement')
                            ->label('Date de versement')
                            ->default(now())->required(),
                        Forms\Components\TextInput::make('certificat_numero')
                            ->label('N° Certificat')->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'             => 'verse',
                            'montant_accorde'    => $data['montant_accorde'],
                            'date_decaissement'  => $data['date_decaissement'],
                            'certificat_numero'  => $data['certificat_numero'],
                        ]);
                        // Mettre à jour montant_decaisse de la régie
                        $regie = $this->getOwnerRecord();
                        $regie->updateQuietly([
                            'montant_decaisse' => $regie->decaissements()
                                ->whereIn('statut', ['accorde', 'verse', 'apure'])
                                ->sum('montant_accorde'),
                        ]);
                        Notification::make()
                            ->title('✅ Décaissement accordé et versé')->success()->send();
                    }),

                // ── Apurer ────────────────────────────────────
                Tables\Actions\Action::make('apurer')
                    ->label('Apurer')
                    ->icon('heroicon-o-clipboard-document-check')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'verse'
                            && auth()->user()?->can('apurer_decaissement_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Apurer la tranche')
                    ->modalDescription('Le compte d\'emploi de cette tranche sera validé.')
                    ->action(function ($record) {
                        $record->update([
                            'statut'        => 'apure',
                            'date_apurement' => now(),
                        ]);
                        Notification::make()->title('✅ Tranche apurée')->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'demande'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
