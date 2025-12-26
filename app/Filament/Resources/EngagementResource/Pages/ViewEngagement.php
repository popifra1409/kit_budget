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
                ->visible(fn($record) => $record->statut !== 'annule')
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement')
                ->modalDescription('Confirmer l\'annulation de cet engagement ? Le budget sera libéré.')
                ->action(function ($record) {
                    // Libérer le budget
                    $ligneEngagement = $record->lignesEngagement()->first();
                    if ($ligneEngagement) {
                        $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                            ->where('nomenclature_id', $ligneEngagement->nomenclature_id)
                            ->first();

                        if ($ligneBudgetaire) {
                            $ligneBudgetaire->annulerEngagement($ligneEngagement->montant);
                        }
                    }

                    $record->statut = 'annule';
                    $record->save();

                    Notification::make()
                        ->title('Engagement annulé')
                        ->warning()
                        ->body('Le budget a été libéré')
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
                            ->label('N° Engagement')
                            ->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('exercice')
                            ->label('Exercice')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('type_engagement')
                            ->label('Type d\'engagement')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'BC' => 'primary',
                                'DA', 'Prime' => 'success',
                                'Mission', 'Formation' => 'warning',
                                'Avance' => 'info',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'provisoire' => 'secondary',
                                'definitif' => 'success',
                                'annule' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'provisoire' => 'Provisoire',
                                'definitif' => 'Définitif',
                                'annule' => 'Annulé',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date d\'engagement')
                            ->date('d/m/Y'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Nomenclature et montant')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.code')
                            ->label('Code nomenclature')
                            ->badge()
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.libelle')
                            ->label('Libellé nomenclature')
                            ->columnSpan(2),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('disponible_ligne')
                            ->label('Disponible actuel (ligne budgétaire)')
                            ->formatStateUsing(function ($record) {
                                $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->where('nomenclature_id', $record->nomenclature_principale_id)
                                    ->first();

                                if (!$ligneBudgetaire) {
                                    return 'N/A';
                                }

                                return number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . ' FCFA';
                            })
                            ->color('info'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('beneficiaire_type_display')
                            ->label('Type de bénéficiaire')
                            ->formatStateUsing(
                                fn($record) =>
                                $record->beneficiaire_type === \App\Models\Fournisseur::class
                                    ? 'Fournisseur'
                                    : 'Personnel'
                            )
                            ->badge()
                            ->color(
                                fn($record) =>
                                $record->beneficiaire_type === \App\Models\Fournisseur::class
                                    ? 'primary'
                                    : 'success'
                            ),

                        Infolists\Components\TextEntry::make('beneficiaire')
                            ->label('Bénéficiaire')
                            ->formatStateUsing(fn($record) => $record->getNomBeneficiaire())
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('beneficiaire.email')
                            ->label('Email')
                            ->placeholder('Non renseigné')
                            ->visible(
                                fn($record) =>
                                $record->beneficiaire_type === \App\Models\User::class &&
                                    $record->beneficiaire?->email
                            ),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Document source')
                    ->schema([
                        Infolists\Components\TextEntry::make('engageable_type')
                            ->label('Type de document')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'App\Models\BonCommande' => 'Bon de Commande',
                                'App\Models\DecisionAdministrative' => 'Décision Administrative',
                                null => 'Manuel (création directe)',
                                default => 'Autre',
                            })
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'App\Models\BonCommande' => 'primary',
                                'App\Models\DecisionAdministrative' => 'success',
                                null => 'secondary',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('reference_document')
                            ->label('Référence')
                            ->placeholder('Non renseignée')
                            ->copyable()
                            ->visible(fn($record) => $record->reference_document),

                        Infolists\Components\TextEntry::make('engageable.numero')
                            ->label('N° Document source')
                            ->placeholder('N/A')
                            ->visible(fn($record) => $record->engageable_id)
                            ->copyable(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                Infolists\Components\Section::make('Lignes d\'engagement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lignesEngagement')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_ligne')
                                    ->label('#'),

                                Infolists\Components\TextEntry::make('nomenclature.code')
                                    ->label('Nomenclature')
                                    ->badge()
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('libelle')
                                    ->label('Libellé')
                                    ->limit(50),

                                Infolists\Components\TextEntry::make('montant')
                                    ->label('Montant')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' FCFA'
                                    )
                                    ->weight('bold')
                                    ->color('success'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => $record->observations),

                Infolists\Components\Section::make('Informations système')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
