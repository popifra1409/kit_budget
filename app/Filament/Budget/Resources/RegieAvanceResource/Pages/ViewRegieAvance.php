<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewRegieAvance extends ViewRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'actif'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('N° RAV')->copyable()->weight('bold'),

                    Infolists\Components\TextEntry::make('libelle')
                        ->label('Désignation')->columnSpan(2),

                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn($state) => match ($state) {
                            'actif'    => 'success',
                            'suspendu' => 'warning',
                            'cloture'  => 'danger',
                            default    => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match ($state) {
                            'actif'    => 'Actif',
                            'suspendu' => 'Suspendu',
                            'cloture'  => 'Clôturé',
                            default    => $state,
                        }),

                    Infolists\Components\TextEntry::make('responsable.name')
                        ->label('Responsable'),

                    Infolists\Components\TextEntry::make('exercice.annee')
                        ->label('Exercice')->badge()->color('info'),

                    Infolists\Components\TextEntry::make('date_creation')
                        ->label('Date de création')->date('d/m/Y'),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Situation financière')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_alloue')
                        ->label('💰 Montant alloué')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('info')->weight('bold'),

                    Infolists\Components\TextEntry::make('montant_decaisse')
                        ->label('🏦 Total décaissé')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('warning'),

                    Infolists\Components\TextEntry::make('montant_depense')
                        ->label('💸 Total dépensé')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('danger'),

                    Infolists\Components\TextEntry::make('montant_disponible')
                        ->label('✅ Disponible')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color(
                            fn($record) =>
                            $record->montant_disponible < 0 ? 'danger' : 'success'
                        )
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('taux_consommation')
                        ->label('📊 Taux consommation')
                        ->getStateUsing(
                            fn($record) =>
                            number_format($record->taux_consommation, 1) . '%'
                        )
                        ->badge()
                        ->color(fn($record) => match (true) {
                            $record->taux_consommation >= 90 => 'danger',
                            $record->taux_consommation >= 70 => 'warning',
                            default                          => 'success',
                        }),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Décision source')
                ->schema([
                    Infolists\Components\TextEntry::make('decisionAdministrative.numero')
                        ->label('N° DA')->copyable()
                        ->url(fn($record) => $record->decisionAdministrative
                            ? route(
                                'filament.budget.resources.decision-administratives.view',
                                $record->decisionAdministrative
                            )
                            : null)
                        ->color('primary'),

                    Infolists\Components\TextEntry::make('decisionAdministrative.objet')
                        ->label('Objet DA')->columnSpan(2),
                ])
                ->columns(3)
                ->visible(fn($record) => $record->decision_administrative_id),
        ]);
    }
}
