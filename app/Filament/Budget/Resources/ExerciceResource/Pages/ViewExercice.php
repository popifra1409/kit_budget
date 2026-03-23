<?php

namespace App\Filament\Budget\Resources\ExerciceResource\Pages;

use App\Filament\Budget\Resources\ExerciceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewExercice extends ViewRecord
{
    protected static string $resource = ExerciceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // Action : Activer
            Actions\Action::make('activer')
                ->label('Activer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->estBrouillon())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->activer(auth()->user());
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // Action : Clôturer
            Actions\Action::make('cloturer')
                ->label('Clôturer cet exercice')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn() => $this->record->estActif())
                ->requiresConfirmation()
                ->modalHeading('Clôturer l\'exercice ' . $this->record->annee)
                ->modalDescription(function () {
                    $service = app(\App\Services\ValidationExerciceService::class);
                    $validation = $service->validerCloture($this->record);

                    $description = "";

                    // Erreurs
                    if (!empty($validation['erreurs'])) {
                        $description .= "### ❌ Erreurs bloquantes\n\n";
                        foreach ($validation['erreurs'] as $erreur) {
                            $description .= "- {$erreur}\n";
                        }
                        $description .= "\n";
                    }

                    // Avertissements
                    if (!empty($validation['avertissements'])) {
                        $description .= "### ⚠️ Avertissements\n\n";
                        foreach ($validation['avertissements'] as $avert) {
                            $description .= "- {$avert}\n";
                        }
                        $description .= "\n";
                    }

                    // Informations
                    $description .= "### 📊 Informations\n\n";
                    foreach ($validation['infos'] as $info) {
                        $description .= "- {$info}\n";
                    }
                    $description .= "\n";

                    // Statistiques budgétaires
                    if (isset($validation['stats'])) {
                        $stats = $validation['stats'];
                        $description .= "### 💰 Budget\n\n";
                        $description .= "- **Total** : " . number_format($stats['budget_total'], 0, ',', ' ') . " FCFA\n";
                        $description .= "- **Engagé** : " . number_format($stats['engage_total'], 0, ',', ' ') . " FCFA\n";
                        $description .= "- **Disponible** : " . number_format($stats['disponible'], 0, ',', ' ') . " FCFA\n";
                        $description .= "- **Taux d'exécution** : " . number_format($stats['taux_execution'], 2) . "%\n\n";
                    }

                    if ($validation['valid']) {
                        $description .= "---\n\n";
                        $description .= "✅ **L'exercice peut être clôturé.**\n\n";
                        $description .= "⚠️ **ATTENTION** : Une fois clôturé, l'exercice ne pourra plus être modifié (sauf par un super admin).";
                    } else {
                        $description .= "---\n\n";
                        $description .= "❌ **L'exercice ne peut PAS être clôturé. Corrigez les erreurs ci-dessus.**";
                    }

                    return new \Illuminate\Support\HtmlString(
                        \Illuminate\Support\Str::markdown($description)
                    );
                })
                ->modalSubmitActionLabel('Clôturer définitivement')
                ->modalCancelActionLabel('Annuler')
                ->modalWidth('3xl')
                ->disabled(function () {
                    $service = app(\App\Services\ValidationExerciceService::class);
                    $validation = $service->validerCloture($this->record);
                    return !$validation['valid'];
                })
                ->action(function () {
                    try {
                        // Mettre à jour les statistiques
                        $this->record->mettreAJourStatistiques();

                        // Clôturer
                        $this->record->cloturer(auth()->user());

                        \Filament\Notifications\Notification::make()
                            ->title('Exercice clôturé avec succès')
                            ->success()
                            ->body("L'exercice {$this->record->annee} a été clôturé. Il ne peut plus être modifié.")
                            ->duration(5000)
                            ->send();

                        // Rediriger vers la liste
                        $this->redirect(static::getResource()::getUrl('index'));
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Erreur lors de la clôture')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // Action : Archiver
            Actions\Action::make('archiver')
                ->label('Archiver')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn() => $this->record->estCloture())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->archiver(auth()->user());
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // Action : Statistiques
            Actions\Action::make('statistiques')
                ->label('Mettre à jour statistiques')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $this->record->mettreAJourStatistiques();
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informations générales')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('annee')
                                    ->label('Année')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('primary'),

                                Components\TextEntry::make('statut')
                                    ->label('Statut')
                                    ->badge()
                                    ->color(fn($record) => $record->getCouleurStatut())
                                    ->formatStateUsing(fn($record) => $record->getBadgeStatut()),

                                Components\IconEntry::make('actif')
                                    ->label('Actif')
                                    ->boolean()
                                    ->size('lg'),
                            ]),

                        Components\TextEntry::make('libelle')
                            ->label('Libellé')
                            ->columnSpanFull(),

                        Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('Aucune description'),
                    ]),

                Components\Section::make('Période')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('date_debut')
                                    ->label('Date de début')
                                    ->date('d/m/Y'),

                                Components\TextEntry::make('date_fin')
                                    ->label('Date de fin')
                                    ->date('d/m/Y'),
                            ]),
                    ]),

                Components\Section::make('Historique')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('date_cloture')
                                    ->label('Date de clôture')
                                    ->date('d/m/Y H:i')
                                    ->placeholder('—'),

                                Components\TextEntry::make('cloturePar.name')
                                    ->label('Clôturé par')
                                    ->placeholder('—'),

                                Components\TextEntry::make('date_archive')
                                    ->label('Date d\'archivage')
                                    ->date('d/m/Y H:i')
                                    ->placeholder('—'),

                                Components\TextEntry::make('archivePar.name')
                                    ->label('Archivé par')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->visible(fn($record) => $record->estCloture() || $record->estArchive())
                    ->collapsible(),

                Components\Section::make('Reconduction')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('exerciceSource.annee')
                                    ->label('Exercice source')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('Aucun'),

                                Components\IconEntry::make('reconduction_effectuee')
                                    ->label('Reconduction effectuée')
                                    ->boolean(),
                            ]),
                    ])
                    ->collapsible(),

                Components\Section::make('Statistiques')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('statistiques.nb_programmes')
                                    ->label('Programmes')
                                    ->badge()
                                    ->color('primary')
                                    ->default(0),

                                Components\TextEntry::make('statistiques.nb_budgets')
                                    ->label('Budgets')
                                    ->badge()
                                    ->color('success')
                                    ->default(0),

                                Components\TextEntry::make('statistiques.nb_bordereaux')
                                    ->label('Bordereaux')
                                    ->badge()
                                    ->color('warning')
                                    ->default(0),
                            ]),

                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('statistiques.montant_total_ae')
                                    ->label('Total AE')
                                    ->money('XAF')
                                    ->default(0),

                                Components\TextEntry::make('statistiques.montant_total_cp')
                                    ->label('Total CP')
                                    ->money('XAF')
                                    ->default(0),
                            ]),

                        Components\TextEntry::make('statistiques.date_calcul')
                            ->label('Dernière mise à jour')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Jamais calculé'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Components\Section::make('Observations')
                    ->schema([
                        Components\TextEntry::make('observations')
                            ->label('')
                            ->columnSpanFull()
                            ->placeholder('Aucune observation'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Components\Section::make('Métadonnées')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Créé le')
                                    ->dateTime('d/m/Y H:i'),

                                Components\TextEntry::make('updated_at')
                                    ->label('Modifié le')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
