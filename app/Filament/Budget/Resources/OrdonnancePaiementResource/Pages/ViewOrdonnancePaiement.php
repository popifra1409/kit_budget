<?php

namespace App\Filament\Budget\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Budget\Resources\OrdonnancePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Models\EtatConfig;
use Filament\Forms;

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
                                ? route('filament.budget.resources.engagements.view', $record->engagement)
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
                            ->label('N° Bon de caisse')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('numero_emission')
                            ->label('N° d\'émission')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('numero_op')
                            ->label('N° OP')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                // Bénéficiaire
                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('beneficiaire.raison_sociale')
                            ->label('Raison sociale')
                            ->default(fn($record) => $record->beneficiaire?->name ?? 'Direction des Impôts')
                            ->placeholder('Direction des Impôts'),

                        Infolists\Components\TextEntry::make('beneficiaire.telephone')
                            ->label('Téléphone')
                            ->placeholder('-')
                            ->visible(fn($record) => $record->beneficiaire),

                        Infolists\Components\TextEntry::make('beneficiaire.email')
                            ->label('Email')
                            ->placeholder('-')
                            ->visible(fn($record) => $record->beneficiaire),
                    ])
                    ->columns(3)
                    ->collapsible(),

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
                    ->visible(fn($record) => $record->statut === 'payee')
                    ->collapsible(),

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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // ── Télécharger OP ───────────────────────────────────────
            Actions\Action::make('telecharger')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('variante')
                        ->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour(
                            $this->record->type_ordonnance === 'impot'
                            ? 'ordonnance_paiement_impot'
                            : 'ordonnance_paiement'
                        ))
                        // ✅ Défaut dynamique depuis la base
                        ->default(fn() => EtatConfig::defautPour(
                            $this->record->type_ordonnance === 'impot'
                            ? 'ordonnance_paiement_impot'
                            : 'ordonnance_paiement'
                        )?->code)
                        ->required()
                        ->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $url = route('pdf.telecharger', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]);
                    $this->dispatch('open-url-new-tab', url: $url);
                }),

            // ── Aperçu OP ────────────────────────────────────────────
            Actions\Action::make('apercu')
                ->label('Aperçu PDF')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('variante')
                        ->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour(
                            $this->record->type_ordonnance === 'impot'
                            ? 'ordonnance_paiement_impot'
                            : 'ordonnance_paiement'
                        ))
                        // ✅ Défaut dynamique depuis la base
                        ->default(fn() => EtatConfig::defautPour(
                            $this->record->type_ordonnance === 'impot'
                            ? 'ordonnance_paiement_impot'
                            : 'ordonnance_paiement'
                        )?->code)
                        ->required()
                        ->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $url = route('pdf.afficher', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]);
                    $this->dispatch('open-url-new-tab', url: $url);
                })
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }
}
