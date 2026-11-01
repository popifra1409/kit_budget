<?php

namespace App\Filament\Budget\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Budget\Resources\VirementBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewVirementBudgetaire extends ViewRecord
{
    protected static string $resource = VirementBudgetaireResource::class;

    /**
     * ✅ Mêmes étapes que la liste des mouvements, toutes protégées (VirementBudgetaireResource::transition) :
     *    un refus (acte formel manquant, plafond, ligne déjà engagée…) s'affiche en message, jamais en erreur 500.
     *    Les mouvements votés en collectif se gèrent depuis leur collectif.
     */
    protected function getHeaderActions(): array
    {
        $R = VirementBudgetaireResource::class;
        $motif = fn(string $libelle) => [Forms\Components\Textarea::make('motif')->label($libelle)->required()->rows(2)];
        $apres = fn() => $this->record->refresh();   // la fiche affiche aussitôt le nouveau statut

        /** visible() masque le bouton ; autoriser() refuse l'appel (une action masquée reste appelable). */
        $etape = fn(string $permission, string|array $statutOuTableau) => function ($record) use ($R, $permission, $statutOuTableau) {
            $statuts = (array) $statutOuTableau;
            return in_array($record->statut, $statuts, true) && $R::peutUtiliser($permission, $record);
        };

        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $R::canEdit($record))
                ->authorize(fn($record) => $R::canEdit($record)),

            Actions\Action::make('approuver')
                ->label('Approuver')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible($etape('approuver_virement_budgetaire', 'en_attente'))
                ->authorize(fn($record) => $R::peutUtiliser('approuver_virement_budgetaire', $record))
                ->modalHeading('Approuver le mouvement')
                ->modalDescription(fn($record) => 'Mouvement de ' . number_format((float) $record->montant, 0, ',', ' ') . ' FCFA.')
                ->form(fn($record) => $R::champsActeFormel($record))
                ->action(function ($record, array $data) use ($R, $apres) {
                    $R::autoriser('approuver_virement_budgetaire', $record);
                    $R::approuverAvecActe($record, $data);
                    $apres();
                }),

            Actions\Action::make('executer')
                ->label('Exécuter')
                ->icon('heroicon-o-bolt')->color('primary')
                ->visible($etape('executer_virement_budgetaire', 'approuve'))
                ->authorize(fn($record) => $R::peutUtiliser('executer_virement_budgetaire', $record))
                ->requiresConfirmation()
                ->modalHeading('Exécuter le mouvement')
                ->modalDescription(fn($record) => 'Les crédits (' . number_format((float) $record->montant, 0, ',', ' ') . ' FCFA) seront déplacés. '
                    . 'Une annulation ultérieure passera par une contre-passation.')
                ->action(function ($record) use ($R, $apres) {
                    $R::autoriser('executer_virement_budgetaire', $record);
                    $R::transition(fn() => $record->executer(), 'Mouvement exécuté : lignes budgétaires mises à jour');
                    $apres();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('retirer_approbation')
                    ->label("Retirer l'approbation")
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible($etape('remettre_en_attente_virement_budgetaire', 'approuve'))
                    ->authorize(fn($record) => $R::peutUtiliser('remettre_en_attente_virement_budgetaire', $record))
                    ->form($motif('Motif (ex. : correction du montant)'))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('remettre_en_attente_virement_budgetaire', $record);
                        $R::transition(fn() => $record->remettreEnAttente(auth()->user(), $data['motif']), 'Mouvement remis en attente : il est de nouveau modifiable');
                        $apres();
                    }),

                Actions\Action::make('rejeter')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible($etape('rejeter_virement_budgetaire', ['en_attente', 'approuve']))
                    ->authorize(fn($record) => $R::peutUtiliser('rejeter_virement_budgetaire', $record))
                    ->form($motif('Motif du rejet'))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('rejeter_virement_budgetaire', $record);
                        $R::transition(fn() => $record->rejeter(auth()->user(), $data['motif']), 'Mouvement rejeté');
                        $apres();
                    }),

                Actions\Action::make('recuperer')
                    ->label('Récupérer (remettre en attente)')
                    ->icon('heroicon-o-arrow-path')->color('info')
                    ->visible($etape('remettre_en_attente_virement_budgetaire', 'rejete'))
                    ->authorize(fn($record) => $R::peutUtiliser('remettre_en_attente_virement_budgetaire', $record))
                    ->modalDescription(fn($record) => $record->motif_rejet ? 'Motif du rejet : ' . $record->motif_rejet : null)
                    ->form($motif('Motif de la récupération (corrections prévues)'))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('remettre_en_attente_virement_budgetaire', $record);
                        $R::transition(fn() => $record->remettreEnAttente(auth()->user(), $data['motif']), 'Mouvement récupéré : il est de nouveau modifiable');
                        $apres();
                    }),

                Actions\Action::make('annuler_mouvement')
                    ->label('Annuler le mouvement')
                    ->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible($etape('annuler_virement_budgetaire', ['en_attente', 'approuve', 'rejete']))
                    ->authorize(fn($record) => $R::peutUtiliser('annuler_virement_budgetaire', $record))
                    ->modalDescription('Le mouvement reste consultable, et pourra être réactivé si nécessaire.')
                    ->form($motif("Motif de l'annulation"))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('annuler_virement_budgetaire', $record);
                        $R::transition(fn() => $record->annulerMouvement(auth()->user(), $data['motif']), 'Mouvement annulé');
                        $apres();
                    }),

                Actions\Action::make('reactiver')
                    ->label('Réactiver (remettre en attente)')
                    ->icon('heroicon-o-arrow-path')->color('info')
                    ->visible($etape('remettre_en_attente_virement_budgetaire', 'annule'))
                    ->authorize(fn($record) => $R::peutUtiliser('remettre_en_attente_virement_budgetaire', $record))
                    ->form($motif('Motif de la réactivation'))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('remettre_en_attente_virement_budgetaire', $record);
                        $R::transition(fn() => $record->remettreEnAttente(auth()->user(), $data['motif']), 'Mouvement réactivé : il est de nouveau modifiable');
                        $apres();
                    }),

                Actions\Action::make('annuler_execution')
                    ->label("Annuler l'exécution (contre-passation)")
                    ->icon('heroicon-o-arrow-uturn-down')->color('danger')
                    ->visible($etape('annuler_execution_mouvement_credit', 'execute'))
                    ->authorize(fn($record) => $R::peutUtiliser('annuler_execution_mouvement_credit', $record))
                    ->modalDescription('Les crédits reviennent sur la ligne source, et le mouvement repasse en attente. '
                        . 'Refusé si la ligne destination a déjà engagé les crédits reçus.')
                    ->form($motif('Motif de la contre-passation'))
                    ->action(function ($record, array $data) use ($R, $apres) {
                        $R::autoriser('annuler_execution_mouvement_credit', $record);
                        $R::transition(fn() => $record->annulerExecution(auth()->user(), $data['motif']), 'Exécution annulée : crédits restitués à la ligne source');
                        $apres();
                    }),
            ])->label('Autres actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
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
                                'en_attente' => 'gray',
                                'approuve' => 'success',
                                'execute' => 'primary',
                                'rejete' => 'danger',
                                'annule' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'en_attente' => 'En attente',
                                'approuve' => 'Approuvé',
                                'execute' => 'Exécuté',
                                'rejete' => 'Rejeté',
                                'annule' => 'Annulé',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('type_mouvement')
                            ->label('Type')
                            ->badge()->color('info')
                            ->formatStateUsing(fn($state, $record) => \App\Services\Budget\MouvementCreditService::libelleType(
                                app(\App\Services\Budget\MouvementCreditService::class)->typeDe($record)
                            )),

                        Infolists\Components\TextEntry::make('date_virement')
                            ->label('Date du virement')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('montant')
                            ->label('Montant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Ligne Source (qui perd du budget)')
                    ->schema([
                        Infolists\Components\TextEntry::make('ligneSource.nomenclature.code')
                            ->label('Code')
                            ->badge()
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('ligneSource.nomenclature.libelle')
                            ->label('Nomenclature')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('ligneSource.budget_rectifie')
                            ->label('Budget rectifié avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                        Infolists\Components\TextEntry::make('ligneSource.disponible_engagement')
                            ->label('Disponible avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color(fn($state) => $state > 0 ? 'success' : 'danger'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Ligne Destination (qui reçoit du budget)')
                    ->schema([
                        Infolists\Components\TextEntry::make('ligneDestination.nomenclature.code')
                            ->label('Code')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('ligneDestination.nomenclature.libelle')
                            ->label('Nomenclature')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('ligneDestination.budget_rectifie')
                            ->label('Budget rectifié avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                        Infolists\Components\TextEntry::make('ligneDestination.disponible_engagement')
                            ->label('Disponible avant')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Justification')
                    ->schema([
                        Infolists\Components\TextEntry::make('motif')
                            ->label('Motif')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('reference_decision')
                            ->label('Référence de la décision')
                            ->placeholder('Non renseignée — demandée à l\'approbation'),

                        Infolists\Components\TextEntry::make('date_acte')
                            ->label('Date de la décision')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('motif_rejet')
                            ->label('Motif du rejet')
                            ->visible(fn($record) => filled($record->motif_rejet)),

                        Infolists\Components\TextEntry::make('motif_annulation')
                            ->label('Motif de l\'annulation')
                            ->visible(fn($record) => filled($record->motif_annulation)),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->placeholder('En attente de validation'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->statut !== 'en_attente'),
            ]);
    }
}
