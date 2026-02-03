<?php

namespace App\Filament\Resources\EngagementResource\Pages;

use App\Filament\Resources\EngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewEngagement extends ViewRecord
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'provisoire' && !$record->engageable_id),

            Actions\Action::make('valider')
                ->label('Valider (Définitif)')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->statut === 'provisoire')
                ->requiresConfirmation()
                ->modalHeading('Valider l\'engagement')
                ->modalDescription(
                    fn($record) =>
                    "Passer l'engagement {$record->numero} en statut définitif ?\n" .
                        "Montant: " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA"
                )
                ->action(function ($record) {
                    $record->statut = 'definitif';
                    $record->save();

                    Notification::make()
                        ->title('Engagement validé')
                        ->success()
                        ->body('L\'engagement est maintenant définitif')
                        ->send();
                }),

            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->statut !== 'annule' && $record->peutEtreAnnule())
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement')
                ->modalDescription('Confirmer l\'annulation de cet engagement ? Le budget sera libéré.')
                ->action(function ($record) {
                    try {
                        // ✅ SOLUTION 1 : Utiliser la méthode annuler() du modèle (RECOMMANDÉ)
                        $record->annuler();

                        Notification::make()
                            ->title('Engagement annulé')
                            ->success()
                            ->body("L'engagement {$record->numero} a été annulé. Le budget a été libéré.")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur lors de l\'annulation')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        throw $e;
                    }
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
                            ->label('Numéro'),
                        Infolists\Components\TextEntry::make('reference_document')
                            ->label('Référence document'),
                        Infolists\Components\TextEntry::make('type_engagement')
                            ->label('Type'),
                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date engagement')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->money('XAF'),
                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'provisoire' => 'warning',
                                'definitif' => 'success',
                                'annule' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Budget et nomenclature')
                    ->schema([
                        Infolists\Components\TextEntry::make('budget.nom')
                            ->label('Budget'),
                        Infolists\Components\TextEntry::make('exercice.annee')
                            ->label('Exercice'),
                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.code')
                            ->label('Code nomenclature'),
                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.libelle')
                            ->label('Libellé nomenclature')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('beneficiaire_type')
                            ->label('Type de bénéficiaire')
                            ->formatStateUsing(fn($state) => class_basename($state)),
                        Infolists\Components\TextEntry::make('beneficiaire.name')
                            ->label('Nom')
                            ->default(fn($record) => $record->beneficiaire->raison_sociale ?? $record->beneficiaire->name ?? 'N/A'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Document source')
                    ->schema([
                        Infolists\Components\TextEntry::make('engageable_type')
                            ->label('Type de document')
                            ->formatStateUsing(fn($state) => match (class_basename($state)) {
                                'BonCommande' => 'Bon de commande',
                                'DecisionAdministrative' => 'Décision administrative',
                                default => class_basename($state),
                            }),
                        Infolists\Components\TextEntry::make('engageable.numero')
                            ->label('Numéro document'),
                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // ✅ CORRECTION : Utiliser 'lignes' au lieu de 'lignesEngagement'
                Infolists\Components\Section::make('Lignes d\'engagement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lignes')
                            ->label('Détail des lignes')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_ligne')
                                    ->label('N°'),
                                Infolists\Components\TextEntry::make('nomenclature.code')
                                    ->label('Code'),
                                Infolists\Components\TextEntry::make('nomenclature.libelle')
                                    ->label('Libellé'),
                                Infolists\Components\TextEntry::make('montant')
                                    ->label('Montant')
                                    ->money('XAF'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->lignes()->exists()),

                Infolists\Components\Section::make('Ordonnances de paiement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('ordonnancesPaiement')
                            ->label('Liste des ordonnances')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero')
                                    ->label('Numéro'),
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Type')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('montant_brut')
                                    ->label('Montant')
                                    ->money('XAF'),
                                Infolists\Components\TextEntry::make('date_emission')
                                    ->label('Date émission')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('statut')
                                    ->label('Statut')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'emis' => 'success',
                                        'paye' => 'success',
                                        'annule' => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(5),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->ordonnancesPaiement()->exists()),

                Infolists\Components\Section::make('Historique')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
