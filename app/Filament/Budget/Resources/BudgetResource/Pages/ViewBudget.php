<?php

namespace App\Filament\Budget\Resources\BudgetResource\Pages;

use App\Filament\Budget\Resources\BudgetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Code'),
                        Infolists\Components\TextEntry::make('libelle')
                            ->label('Libellé'),
                        Infolists\Components\TextEntry::make('exercice')
                            ->label('Exercice')
                            ->badge(),
                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'elaboration' => 'gray',
                                'adopte' => 'success',
                                'execution' => 'warning',
                                'cloture' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'elaboration' => 'Élaboration',
                                'adopte' => 'Adopté',
                                'execution' => 'Exécution',
                                'cloture' => 'Clôturé',
                                default => $state,
                            }),
                        Infolists\Components\TextEntry::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('actif')
                            ->label('Actif')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'danger'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Synthèse budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('budget_total_initial')
                            ->label('Budget Initial')
                            ->getStateUsing(fn($record) => number_format($record->getBudgetTotalInitial(), 0, ',', ' ') . ' FCFA')
                            ->color('info')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget_total_rectifie')
                            ->label('Budget Rectifié')
                            ->getStateUsing(fn($record) => number_format($record->getBudgetTotalRectifie(), 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('total_engage')
                            ->label('Total Engagé')
                            ->getStateUsing(fn($record) => number_format($record->getTotalEngage(), 0, ',', ' ') . ' FCFA')
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('total_liquide')
                            ->label('Total Liquidé')
                            ->getStateUsing(fn($record) => number_format($record->getTotalLiquide(), 0, ',', ' ') . ' FCFA')
                            ->color('primary')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('disponible_total')
                            ->label('Disponible Total')
                            ->getStateUsing(fn($record) => number_format($record->getDisponibleTotal(), 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('taux_engagement')
                            ->label('Taux d\'Engagement')
                            ->getStateUsing(fn($record) => number_format($record->getTauxEngagement(), 2) . '%')
                            ->color(fn($record) => $record->getTauxEngagement() >= 80 ? 'success' : 'warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('taux_execution')
                            ->label('Taux d\'Exécution')
                            ->getStateUsing(fn($record) => number_format($record->getTauxExecution(), 2) . '%')
                            ->color(fn($record) => $record->getTauxExecution() >= 80 ? 'success' : ($record->getTauxExecution() >= 50 ? 'warning' : 'danger'))
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('nombre_lignes')
                            ->label('Nombre de Lignes')
                            ->getStateUsing(fn($record) => $record->lignesBudgetaires()->count())
                            ->badge(),
                    ])
                    ->columns(4),

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
    