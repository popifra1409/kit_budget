<?php

namespace App\Filament\Budget\Resources\AchatDirectResource\Pages;

use App\Filament\Budget\Resources\AchatDirectResource;
use App\Models\ProvisionLigneRegie;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class ViewAchatDirect extends ViewRecord
{
    protected static string $resource = AchatDirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Aperçu ───────────────────────────────────
            Actions\Action::make('apercu')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')->color('info')
                ->modalHeading(fn($record) => 'Aperçu — ' . $record->numero)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(function ($record) {
                    $record->load([
                        'lignes',
                        'regieAvance.responsable',
                        'fournisseur',
                        'ligneRegieAvance.nomenclature',
                        'provisionLigneRegie.decaissement',
                    ]);
                    return view('filament.modals.apercu-achat-direct', [
                        'depense' => $record,
                    ]);
                }),

            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'brouillon'),

            // ── Valider ───────────────────────────────────
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider l\'achat direct')
                ->visible(
                    fn($record) =>
                    $record->statut === 'brouillon'
                        && auth()->user()?->can('valider_depense_regie')
                )
                ->action(function ($record) {
                    try {
                        $record->load('lignes');
                        $totalTtc = $record->lignes->sum('montant_ttc');
                        $totalNap = $record->lignes->sum('montant_net');
                        $totalIr  = $record->lignes->sum('montant_ir');
                        $totalTva = $record->lignes->sum('montant_tva');
                        $totalHt  = $record->lignes->sum('montant_ht');

                        if ($record->provision_ligne_regie_id) {
                            ProvisionLigneRegie::findOrFail(
                                $record->provision_ligne_regie_id
                            )->debiter($totalTtc);
                        }

                        $record->update([
                            'statut'      => 'valide',
                            'montant_ht'  => $totalHt,
                            'montant_tva' => $totalTva,
                            'montant_ttc' => $totalTtc,
                            'montant_ir'  => $totalIr,
                            'net_a_payer' => $totalNap,
                        ]);

                        Notification::make()->title('✅ Validé')->success()->send();
                        $this->refreshFormData(['statut']);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Retour brouillon ──────────────────────────
            Actions\Action::make('retour_brouillon')
                ->label('Retour brouillon')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Retourner en brouillon')
                ->modalDescription('La provision sera créditée et l\'achat repassera en brouillon.')
                ->visible(
                    fn($record) =>
                    $record->statut === 'valide'
                        && auth()->user()?->can('valider_depense_regie')
                )
                ->form([
                    \Filament\Forms\Components\Textarea::make('motif_retour')
                        ->label('Motif du retour')->rows(2)->required(),
                ])
                ->action(function ($record, array $data) {
                    try {
                        if ($record->provision_ligne_regie_id) {
                            ProvisionLigneRegie::find($record->provision_ligne_regie_id)
                                ?->crediter($record->montant_ttc);
                        }

                        $record->update([
                            'statut'       => 'brouillon',
                            'montant_ht'   => 0,
                            'montant_tva'  => 0,
                            'montant_ttc'  => 0,
                            'montant_ir'   => 0,
                            'net_a_payer'  => 0,
                            'observations' => ($record->observations ?? '')
                                . "\n--- RETOUR BROUILLON LE "
                                . now()->format('d/m/Y H:i')
                                . " par " . auth()->user()->name . " ---\n"
                                . $data['motif_retour'],
                        ]);

                        Notification::make()
                            ->title('↩ Retourné en brouillon')->warning()
                            ->body('Provision créditée — corrigez et revalidez.')
                            ->send();
                        $this->refreshFormData(['statut']);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')
                            ->danger()->body($e->getMessage())->send();
                    }
                }),

            // ── Payer ─────────────────────────────────────
            Actions\Action::make('payer')
                ->label('Marquer payé')
                ->icon('heroicon-o-banknotes')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer le paiement')
                ->visible(
                    fn($record) =>
                    $record->statut === 'valide'
                        && auth()->user()?->can('valider_depense_regie')
                )
                ->form([
                    \Filament\Forms\Components\DatePicker::make('date_paiement')
                        ->label('Date de paiement')->default(now())->required(),
                    \Filament\Forms\Components\TextInput::make('reference_paiement')
                        ->label('Référence')->maxLength(100),
                ])
                ->action(function ($record, array $data) {
                    $record->update([
                        'statut'       => 'paye',
                        'observations' => ($record->observations ?? '')
                            . "\n--- PAYÉ LE " . now()->format('d/m/Y')
                            . " (réf: " . ($data['reference_paiement'] ?? '—') . ") ---",
                    ]);
                    Notification::make()->title('✅ Payé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Annuler (brouillon uniquement) ────────────
            Actions\Action::make('annuler')
                ->label('Annuler définitivement')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler définitivement')
                ->modalDescription('⚠️ Action irréversible.')
                ->visible(
                    fn($record) =>
                    $record->statut === 'brouillon'
                        && auth()->user()?->can('annuler_depense_regie')
                )
                ->form([
                    \Filament\Forms\Components\Textarea::make('motif')
                        ->label('Motif')->rows(2)->required(),
                ])
                ->action(function ($record, array $data) {
                    $record->update([
                        'statut'       => 'annule',
                        'observations' => ($record->observations ?? '')
                            . "\n--- ANNULÉ LE " . now()->format('d/m/Y')
                            . " par " . auth()->user()->name . " ---\n"
                            . $data['motif'],
                    ]);
                    Notification::make()->title('🔴 Annulé')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ══ Statut + Totaux ═══════════════════════════════
            Infolists\Components\Section::make('Situation')
                ->schema([
                    Infolists\Components\Grid::make(5)->schema([

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')->badge()->size('xl')
                            ->color(fn($state) => match ($state) {
                                'brouillon' => 'gray',
                                'valide'    => 'warning',
                                'paye'      => 'success',
                                'annule'    => 'danger',
                                default     => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'brouillon' => '🔵 Brouillon',
                                'valide'    => '🟡 Validé',
                                'paye'      => '✅ Payé',
                                'annule'    => '🔴 Annulé',
                                default     => $state,
                            }),

                        // ✅ Totaux depuis les lignes
                        Infolists\Components\TextEntry::make('total_ht')
                            ->label('Total HT')
                            ->getStateUsing(
                                fn($record) =>
                                number_format($record->lignes->sum('montant_ht'), 0, ',', ' ')
                                    . ' FCFA'
                            ),

                        Infolists\Components\TextEntry::make('total_tva')
                            ->label('Total TVA')
                            ->getStateUsing(
                                fn($record) =>
                                number_format($record->lignes->sum('montant_tva'), 0, ',', ' ')
                                    . ' FCFA'
                            ),

                        Infolists\Components\TextEntry::make('total_ttc')
                            ->label('Total TTC')
                            ->getStateUsing(
                                fn($record) =>
                                number_format($record->lignes->sum('montant_ttc'), 0, ',', ' ')
                                    . ' FCFA'
                            )
                            ->weight(FontWeight::Bold),

                        Infolists\Components\TextEntry::make('total_ir')
                            ->label('Total IR')
                            ->getStateUsing(
                                fn($record) =>
                                number_format($record->lignes->sum('montant_ir'), 0, ',', ' ')
                                    . ' FCFA'
                            )
                            ->color('warning'),
                    ]),

                    // ✅ NAP mis en évidence
                    Infolists\Components\TextEntry::make('total_nap')
                        ->label('✅ Total Net à Payer')
                        ->getStateUsing(
                            fn($record) =>
                            number_format($record->lignes->sum('montant_net'), 0, ',', ' ')
                                . ' FCFA'
                        )
                        ->size('xl')
                        ->weight(FontWeight::Bold)
                        ->color('success')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->icon('heroicon-o-banknotes'),

            // ══ Identification ════════════════════════════════
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro')
                            ->weight(FontWeight::Bold)->copyable()
                            ->icon('heroicon-o-hashtag'),

                        Infolists\Components\TextEntry::make('date_depense')
                            ->label('Date')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar-days'),

                        Infolists\Components\TextEntry::make('regieAvance.numero')
                            ->label('Régie')
                            ->badge()->color('info'),
                    ]),

                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet de la dépense')
                        ->weight(FontWeight::Medium)
                        ->columnSpanFull(),

                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('regieAvance.libelle')
                            ->label('Désignation régie'),

                        Infolists\Components\TextEntry::make('ligneRegieAvance.nomenclature.code')
                            ->label('Nomenclature')
                            ->badge()->color('gray'),
                    ]),
                ])
                ->columns(3)
                ->icon('heroicon-o-identification'),

            // ══ Fournisseur ═══════════════════════════════════
            Infolists\Components\Section::make('Fournisseur')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Fournisseur référencé')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('fournisseur_libre')
                            ->label('Fournisseur libre')
                            ->placeholder('—'),
                    ]),
                ])
                ->columns(2)
                ->icon('heroicon-o-building-storefront'),

            // ══ Lignes de dépenses ════════════════════════════
            Infolists\Components\Section::make('Détail des lignes')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\Grid::make(6)->schema([

                                Infolists\Components\TextEntry::make('nature_depense')
                                    ->label('Désignation')
                                    ->weight(FontWeight::Medium)
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('quantite')
                                    ->label('Qté')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 2, ',', ' ')
                                    ),

                                Infolists\Components\TextEntry::make('montant_ht')
                                    ->label('MHT')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' F'
                                    ),

                                Infolists\Components\TextEntry::make('montant_tva')
                                    ->label('TVA')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' F'
                                    ),

                                Infolists\Components\TextEntry::make('montant_ttc')
                                    ->label('TTC')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' F'
                                    )
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('montant_ir')
                                    ->label('IR')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' F'
                                    )
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('montant_net')
                                    ->label('NAP Total')
                                    ->formatStateUsing(
                                        fn($state) =>
                                        number_format($state, 0, ',', ' ') . ' F'
                                    )
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('observations')
                                    ->label('Obs.')
                                    ->placeholder('—'),
                            ]),
                        ])
                        ->columnSpanFull(),
                ])
                ->icon('heroicon-o-list-bullet'),

            // ══ Provision ════════════════════════════════════
            Infolists\Components\Section::make('Provision débitée')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make(
                            'provisionLigneRegie.ligneRegie.nomenclature.code'
                        )
                            ->label('Nomenclature')->badge()->color('gray'),

                        Infolists\Components\TextEntry::make(
                            'provisionLigneRegie.decaissement.libelle_tranche'
                        )
                            ->label('Tranche'),

                        Infolists\Components\TextEntry::make(
                            'provisionLigneRegie.montant_disponible'
                        )
                            ->label('Solde provision restant')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format($state ?? 0, 0, ',', ' ') . ' FCFA'
                            )
                            ->color('info'),
                    ]),
                ])
                ->visible(fn($record) => $record->provision_ligne_regie_id !== null)
                ->icon('heroicon-o-banknotes'),

            // ══ Justificatif ══════════════════════════════════
            Infolists\Components\Section::make('Justificatif')
                ->schema([
                    Infolists\Components\TextEntry::make('justificatif_fichier')
                        ->label('Fichier joint')
                        ->placeholder('Aucun justificatif')
                        ->url(fn($state) => $state ? asset('storage/' . $state) : null)
                        ->openUrlInNewTab(),
                ])
                ->visible(fn($record) => !empty($record->justificatif_fichier))
                ->collapsible()
                ->icon('heroicon-o-paper-clip'),

            // ══ Observations ══════════════════════════════════
            Infolists\Components\Section::make('Observations')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('')->placeholder('Aucune observation')
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => !empty($record->observations))
                ->collapsible()->collapsed()
                ->icon('heroicon-o-chat-bubble-left-right'),

            // ══ Métadonnées ═══════════════════════════════════
            Infolists\Components\Section::make('Métadonnées')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-clock'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')->dateTime('d/m/Y à H:i')
                            ->since()->icon('heroicon-o-clock'),
                    ]),
                ])
                ->collapsible()->collapsed()
                ->icon('heroicon-o-information-circle'),
        ]);
    }
}
