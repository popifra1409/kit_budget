<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditBonCommande extends EditRecord
{
    use \App\Filament\Budget\Concerns\HasAgentContext;
    protected static string $resource = BonCommandeResource::class;

    // =========================================================
    // ACTIONS EN-TÊTE
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [
            // ── Aperçu BC ─────────────────────────────────────
            Actions\Action::make('apercu_bc')
                ->label('Aperçu BC')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->outlined()
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(function () {
                    $this->record->load([
                        'lignes.nomenclature',
                        'lignes.referenceMercuriale',
                        'fournisseur',
                        'exercice',
                        'budget',
                        'typeEngagement',
                    ]);
                    return view('filament.modals.apercu-bon-commande', [
                        'bc' => $this->record,
                    ]);
                }),

            Actions\ViewAction::make(),

            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->statut === 'brouillon'),

            // ── Annuler l'engagement (désengager) ─────────────
            Actions\Action::make('desengager')
                ->label('Annuler l\'engagement')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(
                    fn() =>
                    $this->record->engage
                        && $this->record->peutEtreDesengage()
                        && static::getResource()::canDesengager($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement du bon de commande')
                ->modalDescription(function () {
                    // ✅ Requête directe — contourne morphMap
                    $engagement = \App\Models\Engagement::where('engageable_id', $this->record->id)
                        ->where(function ($q) {
                            $q->where('engageable_type', \App\Models\BonCommande::class)
                                ->orWhere('engageable_type', 'bon_commande');
                        })->first();

                    $numEngagement = $engagement?->numero ?? '—';

                    return new \Illuminate\Support\HtmlString(
                        "<div style='color:#dc2626;font-weight:600;'>
                        L'engagement N° <strong>{$numEngagement}</strong> sera supprimé définitivement.<br>
                        Les crédits seront libérés sur la ligne budgétaire.<br><br>
                        Vous pourrez ensuite annuler ou réengager le bon de commande.
                        </div>"
                    );
                })
                ->modalSubmitActionLabel('🔓 Confirmer le désengagement')
                ->modalCancelActionLabel('Annuler')
                ->action(function () {
                    try {
                        $this->record->desengagerBudget();
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ Engagement annulé — crédits libérés')
                            ->success()
                            ->body("Le BC {$this->record->numero} est de nouveau en statut Validé.")
                            ->duration(5000)->send();
                        $this->refreshFormData(['statut', 'engage']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de désengager')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Annuler le BC ─────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn() =>
                    $this->record->peutEtreAnnule()
                        && static::getResource()::canAnnuler($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('info_annulation')
                        ->label('')
                        ->content(
                            fn() => $this->record->engage
                                ? new \Illuminate\Support\HtmlString(
                                    '<div style="background:#fef2f2;border:1px solid #dc2626;
                                             border-radius:.5rem;padding:.75rem;
                                             color:#dc2626;font-weight:600;">
                                ❌ Ce BC est engagé.<br>
                                Veuillez d\'abord cliquer sur "Annuler l\'engagement",
                                puis revenez annuler le BC.</div>'
                                )
                                : new \Illuminate\Support\HtmlString(
                                    '<div style="background:#fef9c3;border:1px solid #ca8a04;
                                             border-radius:.5rem;padding:.75rem;">
                                ⚠️ Le bon de commande sera annulé. Il restera récupérable.</div>'
                                )
                        )
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif d\'annulation')
                        ->rows(3)->required()
                        ->hidden(fn() => $this->record->engage),
                ])
                ->requiresConfirmation()
                ->modalHeading('Annuler le bon de commande')
                ->action(function (array $data) {
                    try {
                        $this->record->annuler($data['motif'] ?? null);
                        $this->record->refresh();
                        Notification::make()
                            ->title('⚠️ BC annulé')
                            ->warning()->send();
                        $this->redirect(
                            static::getResource()::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Récupérer le BC ───────────────────────────────
            Actions\Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(
                    fn() =>
                    $this->record->statut === 'annule'
                        && $this->record->peutEtreRecupere()
                        && static::getResource()::canRecuperer($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('info_recuperation')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="background:#f0fdf4;border:1px solid #16a34a;
                                         border-radius:.5rem;padding:.75rem;">
                            ✅ Le BC sera remis en <strong>Brouillon</strong> — modifiable et réengageable.
                            </div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')
                        ->required()->rows(3)
                        ->placeholder('Ex: Changement de fournisseur, correction des montants...')
                        ->helperText('Indiquez pourquoi vous récupérez ce document'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Récupérer le bon de commande annulé')
                ->modalSubmitActionLabel('🔄 Confirmer la récupération')
                ->modalCancelActionLabel('Annuler')
                ->action(function (array $data) {
                    try {
                        $this->record->recuperer($data['motif']);
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ BC récupéré — remis en Brouillon')
                            ->success()
                            ->body("Vous pouvez maintenant modifier le BC {$this->record->numero}.")
                            ->duration(5000)->send();
                        $this->fillForm();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de récupérer le BC')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),
        ];
    }

    // =========================================================
    // FORM ACTIONS (inchangé)
    // =========================================================
    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),

            Actions\Action::make('save_draft')
                ->label('💾 Sauvegarder brouillon')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $this->save(shouldRedirect: false);
                    Notification::make()
                        ->title('Brouillon sauvegardé')
                        ->success()
                        ->body('Vos modifications ont été enregistrées.')
                        ->duration(2000)->send();
                })
                ->keyBindings(['ctrl+s', 'command+s'])
                ->tooltip('Raccourci : Ctrl+S')
                ->outlined(),
        ];
    }

    // =========================================================
    // CYCLE DE VIE (inchangé)
    // =========================================================
    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->load('lignes');
        $this->fillForm();

        \Log::info("BC rechargé", [
            'numero' => $this->record->numero,
            'nb_lignes' => $this->record->lignes->count(),
        ]);
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('✅ Bon de commande mis à jour')
            ->body("Le bon de commande {$this->record->numero} a été sauvegardé avec succès.")
            ->duration(3000);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        \Log::info("=== AVANT SAUVEGARDE BC ===", [
            'bc_numero' => $this->record->numero,
            'nb_lignes_formulaire' => count($data['lignes'] ?? []),
            'nb_lignes_bd_avant' => $this->record->lignes()->count(),
            'lignes_formulaire' => collect($data['lignes'] ?? [])->map(fn($l) => [
                'id' => $l['id'] ?? 'nouveau',
                'designation' => $l['designation'] ?? '',
                'quantite' => $l['quantite'] ?? 0,
            ])->toArray(),
        ]);

        $data = self::forcerExonerationTVA($data);

        \Log::info("=== APRÈS mutateFormDataBeforeSave ===", [
            'nb_lignes' => count($data['lignes'] ?? []),
        ]);

        return $data;
    }

    protected static function forcerExonerationTVA(array $data): array
    {
        if (!empty($data['exonere_tva'])) {
            $data['montant_tva'] = 0;
            foreach ($data['lignes'] ?? [] as &$ligne) {
                $ligne['taux_tva'] = 0;
                $ligne['montant_tva'] = 0;
                $ligne['montant_ttc'] = $ligne['montant_ht'] ?? 0;
            }
        }
        return $data;
    }
}
