<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewOrdonnancePaiement extends ViewRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Informations générales
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('N° Ordonnance')
                            ->badge()
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('type_ordonnance')
                            ->label('Type')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'standard' => 'Standard (Fournisseur)',
                                'impot' => 'Impôt (Direction des Impôts)',
                                default => $state,
                            })
                            ->badge()
                            ->color(fn($state) => $state === 'standard' ? 'primary' : 'warning'),

                        Infolists\Components\TextEntry::make('engagement.numero')
                            ->label('Engagement')
                            ->url(fn($record) => $record->engagement
                                ? route('filament.admin.resources.engagements.view', $record->engagement)
                                : null)
                            ->color('info'),

                        Infolists\Components\TextEntry::make('statut_label')
                            ->label('Statut')
                            ->badge()
                            ->color(fn($record) => $record->statut_color),

                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Montants
                Infolists\Components\Section::make('Détail des montants')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_brut')
                            ->label('Montant brut')
                            ->money('XAF')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_impot')
                            ->label('Montant impôt/IR')
                            ->money('XAF')
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('montant_net')
                            ->label('Montant net à payer')
                            ->money('XAF')
                            ->color('success')
                            ->weight('bold')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('montant_pec')
                            ->label('Montant PEC Médical')
                            ->money('XAF')
                            ->visible(fn($record) => $record->type_ordonnance === 'impot'),
                    ])
                    ->columns(4),

                // Dates et références
                Infolists\Components\Section::make('Dates et références')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('mois_emission')
                            ->label('Mois d\'émission')
                            ->formatStateUsing(fn($state) => $state ? sprintf('%02d', $state) : '-'),

                        Infolists\Components\TextEntry::make('periode')
                            ->label('Période'),

                        Infolists\Components\TextEntry::make('numero_bon')
                            ->label('N° Bon de caisse'),

                        Infolists\Components\TextEntry::make('numero_emission')
                            ->label('N° d\'émission'),

                        Infolists\Components\TextEntry::make('numero_op')
                            ->label('N° OP'),
                    ])
                    ->columns(3),

                // Paiement
                Infolists\Components\Section::make('Informations de paiement')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_paiement')
                            ->label('Date de paiement')
                            ->date('d/m/Y')
                            ->placeholder('Non payé'),

                        Infolists\Components\TextEntry::make('reference_paiement')
                            ->label('Référence de paiement')
                            ->placeholder('Non renseignée'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->statut === 'payee'),

                // Observations
                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->columnSpanFull()
                            ->placeholder('Aucune observation'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => $record->observations),
                // Ordonnances de paiement liées
                Infolists\Components\Section::make('Ordonnances de Paiement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('ordonnancesPaiement')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero')
                                    ->label('N° OP')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('type_ordonnance')
                                    ->label('Type')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'standard' => 'Standard',
                                        'impot' => 'Impôt',
                                        default => $state,
                                    })
                                    ->badge(),

                                Infolists\Components\TextEntry::make('montant_net')
                                    ->label('Montant')
                                    ->money('XAF'),

                                Infolists\Components\TextEntry::make('statut_label')
                                    ->label('Statut')
                                    ->badge()
                                    ->color(fn($record) => $record->statut_color),

                                Infolists\Components\TextEntry::make('date_emission')
                                    ->label('Date')
                                    ->date('d/m/Y'),
                            ])
                            ->columns(5),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->ordonnancesPaiement()->exists()),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
