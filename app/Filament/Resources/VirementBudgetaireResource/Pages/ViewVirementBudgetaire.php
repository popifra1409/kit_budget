<?php

namespace App\Filament\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Resources\VirementBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewVirementBudgetaire extends ViewRecord
{
    protected static string $resource = VirementBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'en_attente'),

            Actions\Action::make('approuver')
                ->label('Approuver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->statut === 'en_attente')
                ->requiresConfirmation()
                ->modalHeading('Approuver le virement')
                ->modalDescription(fn($record) => "Approuver le virement de " . number_format($record->montant, 0, ',', ' ') . " FCFA ?")
                ->action(function ($record) {
                    $record->approuver(auth()->user());
                    Notification::make()
                        ->title('Virement approuvé')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('executer')
                ->label('Exécuter')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn($record) => $record->statut === 'approuve')
                ->requiresConfirmation()
                ->modalHeading('Exécuter le virement')
                ->modalDescription(fn($record) => "Exécuter le virement de " . number_format($record->montant, 0, ',', ' ') . " FCFA ? Cette action mettra à jour les lignes budgétaires.")
                ->action(function ($record) {
                    try {
                        $record->executer();
                        Notification::make()
                            ->title('Virement exécuté avec succès')
                            ->success()
                            ->body('Les lignes budgétaires ont été mises à jour.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('rejeter')
                ->label('Rejeter')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->statut === 'en_attente')
                ->requiresConfirmation()
                ->modalHeading('Rejeter le virement')
                ->modalDescription('Êtes-vous sûr de vouloir rejeter ce virement ?')
                ->action(function ($record) {
                    $record->rejeter(auth()->user());
                    Notification::make()
                        ->title('Virement rejeté')
                        ->warning()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro')
                            ->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'en_attente' => 'gray',
                                'approuve' => 'success',
                                'execute' => 'primary',
                                'rejete' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'en_attente' => 'En attente',
                                'approuve' => 'Approuvé',
                                'execute' => 'Exécuté',
                                'rejete' => 'Rejeté',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_virement')
                            ->label('Date du virement')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('montant')
                            ->label('Montant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Ligne Source (qui perd du budget)')
                    ->schema([
                        Infolists\Components\TextEntry::make('ligneSource.nomenclature.code')
                            ->label('Code')
                            ->badge()
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('ligneSource.nomenclature.libelle')
                            ->label('Nomenclature')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('ligneSource.budget_rectifie')
                            ->label('Budget rectifié avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                        Infolists\Components\TextEntry::make('ligneSource.disponible_engagement')
                            ->label('Disponible avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color(fn($state) => $state > 0 ? 'success' : 'danger'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Ligne Destination (qui reçoit du budget)')
                    ->schema([
                        Infolists\Components\TextEntry::make('ligneDestination.nomenclature.code')
                            ->label('Code')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('ligneDestination.nomenclature.libelle')
                            ->label('Nomenclature')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('ligneDestination.budget_rectifie')
                            ->label('Budget rectifié avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                        Infolists\Components\TextEntry::make('ligneDestination.disponible_engagement')
                            ->label('Disponible avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Justification')
                    ->schema([
                        Infolists\Components\TextEntry::make('motif')
                            ->label('Motif')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('reference_decision')
                            ->label('Référence de la décision')
                            ->placeholder('Non renseignée'),
                    ]),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->placeholder('En attente de validation'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->statut !== 'en_attente'),
            ]);
    }
}
