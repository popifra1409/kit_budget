<?php

namespace App\Filament\Resources\BordereauEngagementResource\Pages;

use App\Filament\Resources\BordereauEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewBordereauEngagement extends ViewRecord
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable()),

            Actions\Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn($record) => $record->statut === 'brouillon')
                ->requiresConfirmation()
                ->modalHeading('Transmettre le bordereau')
                ->modalDescription(
                    fn($record) =>
                    "Transmettre le bordereau {$record->numero} avec {$record->nombre_engagements} engagement(s) " .
                        "pour un montant total de " . number_format($record->montant_total, 0, ',', ' ') . " FCFA ?"
                )
                ->form([
                    Forms\Components\TextInput::make('instance_destinataire')
                        ->label('Instance destinataire')
                        ->required()
                        ->placeholder('Ex: Contrôle Financier, Tutelle, Direction Générale')
                        ->helperText('Vers quelle instance transmettre ce bordereau ?'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $record->transmettre(auth()->user(), $data['instance_destinataire']);
                        Notification::make()
                            ->title('Bordereau transmis avec succès')
                            ->success()
                            ->body("Transmis à {$data['instance_destinataire']}")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur lors de la transmission')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('receptionner')
                ->label('Réceptionner')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('warning')
                ->visible(fn($record) => $record->statut === 'transmis')
                ->requiresConfirmation()
                ->modalHeading('Réceptionner le bordereau')
                ->modalDescription('Confirmer la réception de ce bordereau pour examen ?')
                ->action(function ($record) {
                    try {
                        $record->receptionner(auth()->user());
                        Notification::make()
                            ->title('Bordereau réceptionné')
                            ->success()
                            ->body('Le bordereau est maintenant en cours d\'examen')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('valider')
                ->label('Valider le bordereau')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => in_array($record->statut, ['en_cours', 'transmis']))
                ->requiresConfirmation()
                ->modalHeading('Valider le bordereau')
                ->modalDescription(
                    fn($record) =>
                    "Valider tous les engagements de ce bordereau ? " .
                        "Les {$record->nombre_engagements} engagement(s) passeront en statut définitif."
                )
                ->action(function ($record) {
                    try {
                        $record->valider(auth()->user());
                        Notification::make()
                            ->title('Bordereau validé')
                            ->success()
                            ->body("Tous les engagements ont été approuvés")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('rejeter_total')
                ->label('Rejeter tout')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => in_array($record->statut, ['en_cours', 'transmis']))
                ->requiresConfirmation()
                ->modalHeading('Rejeter le bordereau (total)')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif du rejet')
                        ->required()
                        ->rows(3)
                        ->placeholder('Ex: Pièces justificatives manquantes, crédits insuffisants...'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $record->rejeter(auth()->user(), $data['motif']);
                        Notification::make()
                            ->title('Bordereau rejeté')
                            ->warning()
                            ->body('Tous les engagements ont été rejetés')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('retourner')
                ->label('Retourner')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn($record) => in_array($record->statut, ['en_cours', 'rejete_partiel']))
                ->requiresConfirmation()
                ->modalHeading('Retourner le bordereau')
                ->modalDescription('Retourner le bordereau à l\'émetteur pour corrections ?')
                ->form([
                    Forms\Components\Textarea::make('commentaire')
                        ->label('Commentaire')
                        ->rows(3)
                        ->placeholder('Précisez les corrections à apporter...'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $record->retourner(auth()->user(), $data['commentaire'] ?? '');
                        Notification::make()
                            ->title('Bordereau retourné')
                            ->success()
                            ->body('Le bordereau a été retourné pour corrections')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
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
                                'brouillon' => 'gray',
                                'transmis' => 'info',
                                'en_cours' => 'warning',
                                'valide' => 'success',
                                'rejete_total' => 'danger',
                                'rejete_partiel' => 'danger',
                                'retourne' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'brouillon' => 'Brouillon',
                                'transmis' => 'Transmis',
                                'en_cours' => 'En cours',
                                'valide' => 'Validé',
                                'rejete_partiel' => 'Rejeté partiellement',
                                'rejete_total' => 'Rejeté totalement',
                                'retourne' => 'Retourné',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_transmission')
                            ->label('Date de transmission')
                            ->date('d/m/Y')
                            ->placeholder('Non transmis')
                            ->visible(fn($record) => $record->date_transmission),

                        Infolists\Components\TextEntry::make('instance_destinataire')
                            ->label('Instance destinataire')
                            ->placeholder('Non renseigné')
                            ->visible(fn($record) => $record->instance_destinataire),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Montants')
                    ->schema([
                        Infolists\Components\TextEntry::make('nombre_engagements')
                            ->label('Nombre d\'engagements')
                            ->badge()
                            ->color('info')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_total')
                            ->label('Montant total')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('statistiques')
                            ->label('Statistiques des engagements')
                            ->formatStateUsing(function ($record) {
                                $stats = $record->getStatistiques();
                                return "✅ {$stats['valides']} validés | " .
                                    "⏳ {$stats['en_attente']} en attente | " .
                                    "❌ {$stats['rejetes']} rejetés | " .
                                    "🚫 {$stats['annules']} annulés";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Émetteur')
                    ->schema([
                        Infolists\Components\TextEntry::make('emetteur.name')
                            ->label('Émis par'),

                        Infolists\Components\TextEntry::make('emetteur.email')
                            ->label('Email émetteur'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('receptionniste.name')
                            ->label('Réceptionné par')
                            ->placeholder('Non réceptionné'),

                        Infolists\Components\TextEntry::make('date_reception')
                            ->label('Date de réception')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non réceptionné'),

                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->placeholder('Non validé'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->receptionne_par || $record->valide_par),

                Infolists\Components\Section::make('Rejet')
                    ->schema([
                        Infolists\Components\TextEntry::make('rejeteur.name')
                            ->label('Rejeté par'),

                        Infolists\Components\TextEntry::make('date_rejet')
                            ->label('Date de rejet')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('motif_rejet')
                            ->label('Motif du rejet')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->rejete_par)
                    ->collapsed(),

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
