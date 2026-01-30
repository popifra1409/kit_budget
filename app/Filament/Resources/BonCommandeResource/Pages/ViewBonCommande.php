<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewBonCommande extends ViewRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(false),

            Actions\DeleteAction::make()
                ->visible(fn() => static::getResource()::canDelete($this->record))
                ->requiresConfirmation(),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn($record) => $record->statut === 'brouillon')
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->valider(auth()->user());
                    Notification::make()
                        ->title('BC validé')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('engager')
                ->label('Engager le Budget')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn($record) => $record->statut === 'valide' && ! $record->engage)
                ->requiresConfirmation()
                ->action(fn($record) => $record->engagerBudget()),

            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => ! in_array($record->statut, ['annule', 'livre']))
                ->requiresConfirmation()
                ->action(fn($record) => $record->annuler()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('verrou')
                            ->label('')
                            ->state(fn($record) => $record->estModifiable() ? null : '🔒 Document verrouillé')
                            ->color('danger')
                            ->visible(fn($record) => ! $record->estModifiable()),

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro BC')
                            ->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'brouillon' => 'gray',
                                'valide' => 'warning',
                                'engage' => 'primary',
                                'en_cours' => 'info',
                                'livre_partiellement' => 'success',
                                'livre' => 'success',
                                'annule' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'brouillon' => 'Brouillon',
                                'valide' => 'Validé',
                                'engage' => 'Engagé',
                                'en_cours' => 'En cours',
                                'livre_partiellement' => 'Livré partiellement',
                                'livre' => 'Livré',
                                'annule' => 'Annulé',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_livraison_prevue')
                            ->label('Livraison prévue')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('date_livraison_effective')
                            ->label('Livraison effective')
                            ->date('d/m/Y')
                            ->placeholder('Non livrée')
                            ->visible(fn($record) => $record->date_livraison_effective),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Fournisseur et Service')
                    ->schema([
                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Fournisseur'),

                        Infolists\Components\TextEntry::make('fournisseur.telephone')
                            ->label('Téléphone fournisseur')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('serviceDemandeur.nom')
                            ->label('Service demandeur'),

                        Infolists\Components\TextEntry::make('serviceDemandeur.responsable')
                            ->label('Responsable service')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Détails Financiers')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_ht')
                            ->label('Montant HT')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('info')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_tva')
                            ->label('Montant TVA')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_ttc')
                            ->label('Montant TTC')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_ir')
                            ->label('Montant IR')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('danger')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('net_a_percevoir')
                            ->label('Net à Percevoir')
                            ->formatStateUsing(fn($record) => number_format($record->net_a_percevoir, 0, ',', ' ') . ' FCFA')
                            ->color('primary')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->helperText('HT - IR (montant perçu par le fournisseur)'),

                        Infolists\Components\TextEntry::make('lignes_count')
                            ->label('Nombre de lignes')
                            ->state(fn($record) => $record->lignes->count())
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Engagement Budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('engage')
                            ->label('Budget engagé')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->visible(fn($record) => $record->engage),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date d\'engagement')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->engage),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->engage),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->placeholder('Non validé'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->valide_par),

                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
