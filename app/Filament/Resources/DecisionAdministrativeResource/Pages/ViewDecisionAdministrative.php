<?php

namespace App\Filament\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Resources\DecisionAdministrativeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewDecisionAdministrative extends ViewRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable()),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn($record) => $record->statut === 'brouillon')
                ->requiresConfirmation()
                ->modalHeading('Valider la décision')
                ->modalDescription(
                    fn($record) =>
                    "Valider la décision pour {$record->getNomCompletPersonnel()} d'un montant net de " .
                        number_format($record->montant_net, 0, ',', ' ') . " FCFA ?"
                )
                ->action(function ($record) {
                    $record->valider(auth()->user());
                    Notification::make()
                        ->title('Décision validée')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('engager')
                ->label('Engager le Budget')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn($record) => $record->statut === 'validee' && !$record->engagee)
                ->requiresConfirmation()
                ->modalHeading('Engager le budget')
                ->modalDescription(
                    fn($record) =>
                    "Engager le budget pour un montant net de " .
                        number_format($record->montant_net, 0, ',', ' ') . " FCFA ?"
                )
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Nomenclature budgétaire')
                        ->options(function (callable $get, $record) {
                            return \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                ->with('nomenclature')
                                ->get()
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id => "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} (Dispo: " .
                                        number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Sélectionner la ligne budgétaire (ex: 641100 - Salaires et indemnités)'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $record->engagerBudget($data['nomenclature_id']);
                        Notification::make()
                            ->title('Budget engagé avec succès')
                            ->success()
                            ->body("Engagement créé : " . $record->engagement->numero)
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur lors de l\'engagement')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => !in_array($record->statut, ['annulee', 'payee']))
                ->requiresConfirmation()
                ->modalHeading('Annuler la décision')
                ->modalDescription('Confirmer l\'annulation de cette décision ? Si le budget est engagé, il sera désengagé automatiquement.')
                ->action(function ($record) {
                    $record->annuler();
                    Notification::make()
                        ->title('Décision annulée')
                        ->warning()
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
                            ->label('Numéro DA')
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
                                'validee' => 'warning',
                                'engagee' => 'primary',
                                'ordonnancee' => 'info',
                                'liquidee' => 'success',
                                'payee' => 'success',
                                'annulee' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'brouillon' => 'Brouillon',
                                'validee' => 'Validée',
                                'engagee' => 'Engagée',
                                'ordonnancee' => 'Ordonnancée',
                                'liquidee' => 'Liquidée',
                                'payee' => 'Payée',
                                'annulee' => 'Annulée',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('type_decision')
                            ->label('Type de décision')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'avancement' => 'Avancement',
                                'promotion' => 'Promotion',
                                'prime' => 'Prime',
                                'indemnite' => 'Indemnité',
                                'formation' => 'Formation',
                                'mission' => 'Mission',
                                'affectation' => 'Affectation',
                                'autre' => 'Autre',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_decision')
                            ->label('Date de décision')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_effet')
                            ->label('Date de prise d\'effet')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('date_fin')
                            ->label('Date de fin')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée')
                            ->visible(fn($record) => $record->date_fin),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Personnel concerné')
                    ->schema([
                        Infolists\Components\TextEntry::make('personnel.nom_complet')
                            ->label('Nom')
                            ->default('Non renseigné')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('personnel.matricule')
                            ->label('Matricule')
                            ->badge(),

                        Infolists\Components\TextEntry::make('personnel.fonction')
                            ->label('Fonction'),

                        Infolists\Components\TextEntry::make('personnel.service.nom')
                            ->label('Service'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->personnel_id),

                Infolists\Components\Section::make('Montants')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_brut')
                            ->label('Montant brut')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) $state, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('info')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_cnps')
                            ->label(
                                fn($record) =>
                                'CNPS (' . number_format($record->taux_cnps ?? 0, 2) . '%)'
                            )
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) $state, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('warning')
                            ->visible(fn($record) => ($record->montant_cnps ?? 0) > 0),

                        Infolists\Components\TextEntry::make('montant_irnc')
                            ->label(
                                fn($record) =>
                                'IRNC (' . number_format($record->taux_irnc ?? 0, 2) . '%)'
                            )
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) $state, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('warning')
                            ->visible(fn($record) => ($record->montant_irnc ?? 0) > 0),

                        Infolists\Components\TextEntry::make('autres_retenues')
                            ->label('Autres retenues')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) ($state ?? 0), 0, ',', ' ') . ' FCFA'
                            )
                            ->visible(fn($record) => ($record->autres_retenues ?? 0) > 0),

                        Infolists\Components\TextEntry::make('total_taxes')
                            ->label('Total retenues')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) $state, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('montant_net')
                            ->label('Montant net à payer')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format((float) $state, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Engagement Budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('engagee')
                            ->label('Budget engagé')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->visible(fn($record) => $record->engagee),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date d\'engagement')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->engagee),

                        Infolists\Components\TextEntry::make('engagement.numero')
                            ->label('N° Engagement')
                            ->copyable()
                            ->visible(fn($record) => $record->engagement),
                    ])
                    ->columns(4)
                    ->visible(fn($record) => $record->engagee),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Références')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference_decision')
                            ->label('Référence')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('signataire')
                            ->label('Signataire')
                            ->placeholder('Non renseigné'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateurUser.name')
                            ->label('Validée par')
                            ->placeholder('Non validée'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validée'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->validee_par)
                    ->collapsible()
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
