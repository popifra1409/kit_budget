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
                ->description(fn($record) => (float) $record->montant_disponible < 0
                    ? '⚠️ La consommation dépasse les encaisses versés : le taux supérieur à 100 % correspond à un dépassement réel, à financer.'
                    : null)
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

                    Infolists\Components\TextEntry::make('montant_engage')
                        ->label('🔒 Engagé (déjà consommé)')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('danger')->weight('bold')
                        ->tooltip('BCR au statut engagé + achats directs validés : '
                            . "la régie est consommée dès l'engagement."),

                    Infolists\Components\TextEntry::make('montant_depense')
                        ->label('💸 Livré / payé')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('warning'),

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
                        ->weight('bold')
                        ->tooltip('= Total décaissé − Engagé')
                        // ✅ Dépassement expliqué : la régie a consommé plus que ce qui lui a été versé
                        ->helperText(fn($record) => (float) $record->montant_disponible < 0
                            ? '⚠️ Dépassement à financer : ' . number_format(abs((float) $record->montant_disponible), 0, ',', ' ')
                            . ' FCFA. Approvisionner la régie (nouvelle DA, puis « Accorder & Verser » une tranche).'
                            : null),

                    // ✅ BCR validés ou livrés dont une part n'est pas encore engagée : ils ne
                    //    consomment rien tant qu'ils ne sont pas engagés, mais devront l'être.
                    Infolists\Components\TextEntry::make('engagements_en_attente')
                        ->label('⏳ BCR en attente d\'engagement')
                        ->getStateUsing(function ($record) {
                            $bons = $record->bonsCommande()->whereIn('statut', ['valide', 'livre'])->get();
                            $montant = (float) $bons->sum(fn($b) => $b->resteNonTrace());
                            return number_format($montant, 0, ',', ' ') . ' FCFA (' . $bons->filter(fn($b) => $b->resteNonTrace() > 0.01)->count() . ' BCR)';
                        })
                        ->color(function ($record) {
                            $montant = (float) $record->bonsCommande()->whereIn('statut', ['valide', 'livre'])->get()->sum(fn($b) => $b->resteNonTrace());
                            return $montant > max(0, (float) $record->montant_disponible) + 0.01 ? 'danger' : 'gray';
                        })
                        ->helperText(function ($record) {
                            $montant = (float) $record->bonsCommande()->whereIn('statut', ['valide', 'livre'])->get()->sum(fn($b) => $b->resteNonTrace());
                            $dispo = max(0, (float) $record->montant_disponible);
                            return $montant > $dispo + 0.01
                                ? '⚠️ Dépassent le disponible de ' . number_format($montant - $dispo, 0, ',', ' ') . ' FCFA : ils ne pourront pas tous être engagés sans nouvel encaisse.'
                                : 'Couverts par le disponible.';
                        }),

                    Infolists\Components\TextEntry::make('taux_consommation')
                        ->label('📊 Taux consommation')
                        ->tooltip('Engagé / total décaissé. Au-delà de 100 % : la régie a consommé plus que ses encaisses.')
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

            Infolists\Components\Section::make('Pièces de l\'apurement')
                ->description('Références reprises dans le texte qui précède le compte d\'emploi.')
                ->schema([
                    Infolists\Components\TextEntry::make('resolution_numero')
                        ->label('Résolution de renouvellement')
                        ->placeholder('non renseignée')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('resolution_date')
                        ->label('Date')->date('d/m/Y')->placeholder('—'),

                    Infolists\Components\TextEntry::make('decision_ouverture_numero')
                        ->label('Décision portant ouverture')
                        ->placeholder('non renseignée')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('decision_ouverture_date')
                        ->label('Date')->date('d/m/Y')->placeholder('—'),

                    Infolists\Components\TextEntry::make('decision_designation_numero')
                        ->label('Décision de désignation des responsables')
                        ->placeholder('non renseignée')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('decision_designation_date')
                        ->label('Date')->date('d/m/Y')->placeholder('—'),
                ])
                ->columns(3)
                ->collapsible(),
        ]);
    }
}
