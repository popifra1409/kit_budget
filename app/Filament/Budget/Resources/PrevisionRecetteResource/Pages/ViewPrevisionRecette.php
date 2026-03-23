<?php

namespace App\Filament\Budget\Resources\PrevisionRecetteResource\Pages;

use App\Filament\Budget\Resources\PrevisionRecetteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class ViewPrevisionRecette extends ViewRecord
{
    protected static string $resource = PrevisionRecetteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable()),

            Actions\Action::make('adopter')
                ->label('Adopter')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Adopter la prévision de recettes')
                ->modalDescription('Confirmer l\'adoption de cette prévision de recettes ?')
                ->modalSubmitActionLabel('Adopter')
                ->action(fn($record) => $record->adopter())
                ->visible(fn($record) => $record->estEnElaboration() && auth()->user()->hasAnyRole(['super_admin', 'directeur_general']))
                ->successNotificationTitle('Prévision adoptée'),

            Actions\Action::make('mettre_en_execution')
                ->label('Mettre en Exécution')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn($record) => $record->mettreEnExecution())
                ->visible(fn($record) => $record->estAdopte() && auth()->user()->hasRole('super_admin'))
                ->successNotificationTitle('Prévision en exécution'),

            Actions\Action::make('cloturer')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clôturer la prévision')
                ->modalDescription('Cette action est irréversible. Confirmer ?')
                ->action(fn($record) => $record->cloturer())
                ->visible(fn($record) => $record->estEnExecution() && auth()->user()->hasRole('super_admin'))
                ->successNotificationTitle('Prévision clôturée'),

            Actions\Action::make('reviser')
                ->label('Créer Révision')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Créer une révision')
                ->modalDescription('Créer une nouvelle version rectificative de cette prévision ?')
                ->action(function ($record) {
                    $nouvelle = $record->reviser();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $nouvelle]));
                })
                ->visible(fn($record) => auth()->user()->hasAnyRole(['super_admin', 'chef_service_budget']))
                ->successNotificationTitle('Révision créée'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ==========================================
                // SECTION: STATISTIQUES GLOBALES
                // ==========================================
                Infolists\Components\Section::make('Vue d\'Ensemble')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_prevu_initial')
                                    ->label('Total Prévu Initial')
                                    ->state(fn($record) => number_format($record->getTotalPrevuInitial(), 0, ',', ' ') . ' FCFA')
                                    ->color('info')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('total_prevu_rectifie')
                                    ->label('Total Prévu Rectifié')
                                    ->state(fn($record) => number_format($record->getTotalPrevuRectifie(), 0, ',', ' ') . ' FCFA')
                                    ->color('warning')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('total_recouvre')
                                    ->label('Total Recouvré')
                                    ->state(fn($record) => number_format($record->getTotalRecouvre(), 0, ',', ' ') . ' FCFA')
                                    ->color('success')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('taux_recouvrement')
                                    ->label('Taux de Recouvrement')
                                    ->state(fn($record) => number_format($record->getTauxRecouvrement(), 2) . ' %')
                                    ->badge()
                                    ->color(
                                        fn($record) =>
                                        $record->getTauxRecouvrement() >= 90 ? 'success' : ($record->getTauxRecouvrement() >= 70 ? 'warning' : 'danger')
                                    )
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('ecart_global')
                                    ->label('Écart Global')
                                    ->state(function ($record) {
                                        $ecart = $record->getEcartGlobal();
                                        return ($ecart >= 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') . ' FCFA';
                                    })
                                    ->color(fn($record) => $record->getEcartGlobal() >= 0 ? 'success' : 'danger')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('lignes_count')
                                    ->label('Nombre de Lignes')
                                    ->state(fn($record) => $record->lignesPrevisions()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->icon('heroicon-o-chart-bar'),

                // ==========================================
                // SECTION: INFORMATIONS PRINCIPALES
                // ==========================================
                Infolists\Components\Section::make('Informations')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Code')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->copyMessage('Code copié!')
                                    ->copyMessageDuration(1500),

                                Infolists\Components\TextEntry::make('exercice.annee')
                                    ->label('Exercice')
                                    ->badge()
                                    ->color(fn($record) => $record->exercice_couleur),

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
                                        'elaboration' => 'En Élaboration',
                                        'adopte' => 'Adopté',
                                        'execution' => 'En Exécution',
                                        'cloture' => 'Clôturé',
                                        default => $state,
                                    }),
                            ]),

                        Infolists\Components\TextEntry::make('libelle')
                            ->label('Libellé')
                            ->columnSpanFull()
                            ->weight(FontWeight::Medium),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('date_adoption')
                                    ->label('Date d\'Adoption')
                                    ->date('d/m/Y')
                                    ->placeholder('Non adopté')
                                    ->icon('heroicon-o-calendar'),

                                Infolists\Components\TextEntry::make('date_revision')
                                    ->label('Date de Révision')
                                    ->date('d/m/Y')
                                    ->placeholder('Aucune révision')
                                    ->icon('heroicon-o-calendar'),

                                Infolists\Components\IconEntry::make('actif')
                                    ->label('Actif')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),

                        Infolists\Components\TextEntry::make('observations')
                            ->label('Observations')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull()
                            ->hidden(fn($record) => empty($record->observations)),
                    ])
                    ->columns(3)
                    ->icon('heroicon-o-information-circle'),

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

    protected function getFooterWidgets(): array
    {
        return [
            // Vous pouvez ajouter des widgets personnalisés ici
        ];
    }
}
