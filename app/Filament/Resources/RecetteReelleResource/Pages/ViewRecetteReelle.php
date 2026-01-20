<?php

namespace App\Filament\Resources\RecetteReelleResource\Pages;

use App\Filament\Resources\RecetteReelleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;

class ViewRecetteReelle extends ViewRecord
{
    protected static string $resource = RecetteReelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => !$record->estValidee()),

            Actions\Action::make('comptabiliser')
                ->label('Comptabiliser')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Comptabiliser la recette')
                ->modalDescription('Confirmer la comptabilisation de cette recette ?')
                ->modalSubmitActionLabel('Comptabiliser')
                ->action(fn($record) => $record->comptabiliser())
                ->visible(fn($record) => $record->estEncaissee() && auth()->user()->hasAnyRole(['super_admin', 'agence_comptable']))
                ->successNotificationTitle('Recette comptabilisée'),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider la recette')
                ->modalDescription('Confirmer la validation définitive de cette recette ?')
                ->modalSubmitActionLabel('Valider')
                ->action(fn($record) => $record->valider(auth()->id()))
                ->visible(fn($record) => $record->estComptabilisee() && auth()->user()->hasAnyRole(['super_admin', 'agence_comptable', 'controleur_financier']))
                ->successNotificationTitle('Recette validée'),

            Actions\DeleteAction::make()
                ->visible(fn($record) => !$record->estValidee() && auth()->user()->hasRole('super_admin')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ==========================================
                // SECTION: MONTANT ET STATUT
                // ==========================================
                Infolists\Components\Section::make('Montant')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('montant')
                                    ->label('Montant de la Recette')
                                    ->money('XAF', locale: 'fr')
                                    ->color('success')
                                    ->weight(FontWeight::Bold)
                                    ->size('xl'),

                                Infolists\Components\TextEntry::make('statut')
                                    ->label('Statut')
                                    ->badge()
                                    ->size('xl')
                                    ->color(fn(string $state): string => match ($state) {
                                        'prevue' => 'gray',
                                        'encaissee' => 'info',
                                        'comptabilisee' => 'warning',
                                        'validee' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'prevue' => '📋 Prévue',
                                        'encaissee' => '💰 Encaissée',
                                        'comptabilisee' => '📊 Comptabilisée',
                                        'validee' => '✅ Validée',
                                        default => $state,
                                    }),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->icon('heroicon-o-banknotes'),

                // ==========================================
                // SECTION: IDENTIFICATION
                // ==========================================
                Infolists\Components\Section::make('Identification')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('numero')
                                    ->label('Numéro')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->copyMessage('Numéro copié!')
                                    ->copyMessageDuration(1500)
                                    ->icon('heroicon-o-hashtag'),

                                Infolists\Components\TextEntry::make('exercice.annee')
                                    ->label('Exercice')
                                    ->badge()
                                    ->color(
                                        fn($record) =>
                                        $record->exercice?->estActif() ? 'success' : 'gray'
                                    )
                                    ->icon('heroicon-o-calendar'),

                                Infolists\Components\TextEntry::make('date_recette')
                                    ->label('Date d\'Encaissement')
                                    ->date('d/m/Y')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-calendar-days'),
                            ]),

                        Infolists\Components\TextEntry::make('libelle')
                            ->label('Libellé')
                            ->columnSpanFull()
                            ->weight(FontWeight::Medium)
                            ->size('lg'),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('lignePrevisionRecette.code_nomenclature')
                                    ->label('Code Nomenclature')
                                    ->weight(FontWeight::Bold)
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('lignePrevisionRecette.libelle_nomenclature')
                                    ->label('Libellé Nomenclature')
                                    ->weight(FontWeight::Medium),
                            ]),
                    ])
                    ->columns(3)
                    ->icon('heroicon-o-identification'),

                // ==========================================
                // SECTION: PAYEUR
                // ==========================================
                Infolists\Components\Section::make('Informations Payeur')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('payeur')
                                    ->label('Nom du Payeur')
                                    ->placeholder('Non renseigné')
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::Medium),

                                Infolists\Components\TextEntry::make('mode_paiement')
                                    ->label('Mode de Paiement')
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        'Virement' => 'success',
                                        'Chèque' => 'info',
                                        'Espèces' => 'warning',
                                        'Mobile Money', 'Carte' => 'primary',
                                        default => 'gray',
                                    })
                                    ->icon(fn(?string $state): string => match ($state) {
                                        'Virement' => 'heroicon-o-arrow-path',
                                        'Chèque' => 'heroicon-o-document-text',
                                        'Espèces' => 'heroicon-o-banknotes',
                                        'Mobile Money' => 'heroicon-o-device-phone-mobile',
                                        'Carte' => 'heroicon-o-credit-card',
                                        default => 'heroicon-o-question-mark-circle',
                                    })
                                    ->placeholder('Non renseigné'),

                                Infolists\Components\TextEntry::make('reference_paiement')
                                    ->label('Référence de Paiement')
                                    ->placeholder('Non renseigné')
                                    ->copyable()
                                    ->icon('heroicon-o-document'),
                            ]),
                    ])
                    ->columns(3)
                    ->icon('heroicon-o-user-circle'),

                // ==========================================
                // SECTION: DATES ET COMPTABILISATION
                // ==========================================
                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('date_recette')
                                    ->label('Date d\'Encaissement')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-o-calendar-days'),

                                Infolists\Components\TextEntry::make('date_comptabilisation')
                                    ->label('Date de Comptabilisation')
                                    ->date('d/m/Y')
                                    ->placeholder('Non comptabilisée')
                                    ->icon('heroicon-o-calendar')
                                    ->hidden(fn($record) => !$record->date_comptabilisation),
                            ]),
                    ])
                    ->columns(2)
                    ->icon('heroicon-o-clock'),

                // ==========================================
                // SECTION: VALIDATION
                // ==========================================
                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('validateur.name')
                                    ->label('Validé par')
                                    ->placeholder('Non validée')
                                    ->icon('heroicon-o-user')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('validee_le')
                                    ->label('Validé le')
                                    ->dateTime('d/m/Y à H:i')
                                    ->placeholder('Non validée')
                                    ->icon('heroicon-o-check-badge'),
                            ]),
                    ])
                    ->columns(2)
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn($record) => $record->estValidee()),

                // ==========================================
                // SECTION: OBSERVATIONS
                // ==========================================
                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn($record) => !empty($record->observations)),

                // ==========================================
                // SECTION: MÉTADONNÉES
                // ==========================================
                Infolists\Components\Section::make('Métadonnées')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Créé le')
                                    ->dateTime('d/m/Y à H:i')
                                    ->icon('heroicon-o-clock'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Modifié le')
                                    ->dateTime('d/m/Y à H:i')
                                    ->icon('heroicon-o-clock')
                                    ->since(),
                            ]),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->icon('heroicon-o-information-circle'),
            ]);
    }
}
