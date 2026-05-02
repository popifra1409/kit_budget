<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;

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
