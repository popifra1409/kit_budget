<?php

namespace App\Filament\Resources\TypeEngagementResource\Pages;

use App\Filament\Resources\TypeEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTypeEngagement extends ViewRecord
{
    protected static string $resource = TypeEngagementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Informations générales
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('libelle')
                            ->label('Libellé')
                            ->weight('bold')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('Aucune description'),

                        Infolists\Components\IconEntry::make('actif')
                            ->label('Statut')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\TextEntry::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(2),

                // Seuils de montant
                Infolists\Components\Section::make('Fourchette de montants')
                    ->description('Limites de montants pour ce type d\'engagement')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_min')
                            ->label('Montant minimum')
                            ->money('XAF')
                            ->placeholder('Aucune limite inférieure')
                            ->color('info'),

                        Infolists\Components\TextEntry::make('montant_max')
                            ->label('Montant maximum')
                            ->money('XAF')
                            ->placeholder('Aucune limite supérieure')
                            ->color('info'),

                        Infolists\Components\TextEntry::make('fourchette')
                            ->label('Fourchette applicable')
                            ->getStateUsing(function ($record) {
                                $min = $record->montant_min;
                                $max = $record->montant_max;

                                if ($min && $max) {
                                    return number_format($min, 0, ',', ' ') . " FCFA ≤ Montant < " .
                                        number_format($max, 0, ',', ' ') . " FCFA";
                                } elseif ($min) {
                                    return "Montant ≥ " . number_format($min, 0, ',', ' ') . " FCFA";
                                } elseif ($max) {
                                    return "Montant < " . number_format($max, 0, ',', ' ') . " FCFA";
                                }

                                return "Tous les montants";
                            })
                            ->badge()
                            ->color('success')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Règles IR
                Infolists\Components\Section::make('Règles de calcul IR')
                    ->description('Comment l\'impôt sur le revenu est calculé pour ce type')
                    ->schema([
                        Infolists\Components\TextEntry::make('mode_calcul_ir')
                            ->label('Mode de calcul')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'fixe' => 'Taux fixe',
                                'selon_regime' => 'Selon le régime fiscal',
                                'aucun' => 'Pas d\'IR',
                                default => $state,
                            })
                            ->color(fn(string $state): string => match ($state) {
                                'fixe' => 'primary',
                                'selon_regime' => 'warning',
                                'aucun' => 'secondary',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('taux_ir_fixe')
                            ->label('Taux IR fixe')
                            ->suffix('%')
                            ->visible(fn($record) => $record->mode_calcul_ir === 'fixe')
                            ->weight('bold')
                            ->color('primary')
                            ->size('lg'),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('taux_ir_regime_reel')
                                    ->label('Taux IR - Régime Réel')
                                    ->suffix('%')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('taux_ir_regime_simplifie')
                                    ->label('Taux IR - Régime Simplifié')
                                    ->suffix('%')
                                    ->badge()
                                    ->color('warning'),
                            ])
                            ->visible(fn($record) => $record->mode_calcul_ir === 'selon_regime')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('explication_ir')
                            ->label('Explication')
                            ->getStateUsing(function ($record) {
                                if ($record->mode_calcul_ir === 'fixe') {
                                    return "L'IR est toujours de {$record->taux_ir_fixe}% peu importe le régime fiscal du fournisseur.";
                                } elseif ($record->mode_calcul_ir === 'selon_regime') {
                                    return "L'IR varie selon le régime fiscal : {$record->taux_ir_regime_reel}% pour régime réel, {$record->taux_ir_regime_simplifie}% pour régime simplifié.";
                                } elseif ($record->mode_calcul_ir === 'aucun') {
                                    return "Aucun impôt sur le revenu n'est appliqué pour ce type d'engagement.";
                                }
                                return '-';
                            })
                            ->icon('heroicon-o-information-circle')
                            ->color('info')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Statistiques d'utilisation
                Infolists\Components\Section::make('Statistiques d\'utilisation')
                    ->schema([
                        Infolists\Components\TextEntry::make('nombre_bons_commande')
                            ->label('Bons de commande')
                            ->getStateUsing(fn($record) => $record->bonsCommande()->count())
                            ->badge()
                            ->color('success')
                            ->suffix(fn($record) => $record->bonsCommande()->count() > 1 ? ' BC' : ' BC'),

                        Infolists\Components\TextEntry::make('montant_total_engage')
                            ->label('Montant total engagé')
                            ->getStateUsing(function ($record) {
                                return $record->bonsCommande()
                                    ->where('engage', true)
                                    ->sum('montant_ttc');
                            })
                            ->money('XAF')
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('dernier_bc')
                            ->label('Dernier BC créé')
                            ->getStateUsing(function ($record) {
                                $dernierBc = $record->bonsCommande()
                                    ->latest()
                                    ->first();

                                return $dernierBc ? $dernierBc->created_at->diffForHumans() : 'Aucun BC';
                            })
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                // Métadonnées
                Infolists\Components\Section::make('Informations système')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y à H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y à H:i')
                            ->since(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('duplicate')
                ->label('Dupliquer')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $nouveau = $this->record->replicate();
                    $nouveau->code = $this->record->code . '_COPIE';
                    $nouveau->libelle = $this->record->libelle . ' (Copie)';
                    $nouveau->actif = false;
                    $nouveau->save();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $nouveau]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->bonsCommande()->count() === 0)
                ->requiresConfirmation()
                ->modalDescription('Êtes-vous sûr ? Cette action est irréversible.'),
        ];
    }
}
