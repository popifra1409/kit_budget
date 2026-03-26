<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBonCommande extends EditRecord
{
    protected static string $resource = BonCommandeResource::class;


    protected function getHeaderActions(): array
    {
        return [
            // ── Aperçu BC en cours ──────────────────────────────
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
        ];
    }

    // ✅ NOUVEAU : Boutons du formulaire avec sauvegarde rapide
    protected function getFormActions(): array
    {
        return [
            // Actions par défaut (Sauvegarder, Annuler)
            ...parent::getFormActions(),

            // ✅ NOUVEAU : Bouton de sauvegarde rapide en brouillon
            Actions\Action::make('save_draft')
                ->label('💾 Sauvegarder brouillon')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    // Sauvegarder sans redirection
                    $this->save(shouldRedirect: false);

                    // Notification de succès
                    \Filament\Notifications\Notification::make()
                        ->title('Brouillon sauvegardé')
                        ->success()
                        ->body('Vos modifications ont été enregistrées.')
                        ->duration(2000)
                        ->send();
                })
                // ✅ Raccourci clavier Ctrl+S (Cmd+S sur Mac)
                ->keyBindings(['ctrl+s', 'command+s'])
                ->tooltip('Raccourci : Ctrl+S')
                ->outlined(),
        ];
    }

    protected function afterSave(): void
    {
        // Recharger le BC avec ses lignes
        $this->record->refresh();
        $this->record->load('lignes');

        // ✅ IMPORTANT : Recharger le formulaire
        $this->fillForm();

        \Log::info("BC rechargé", [
            'numero' => $this->record->numero,
            'nb_lignes' => $this->record->lignes->count(),
        ]);
    }


    protected function getRedirectUrl(): ?string
    {
        return null; // Rester sur la même page
    }

    // ✅ NOUVEAU : Message personnalisé après sauvegarde
    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
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

        // Forcer l'exonération TVA
        $data = self::forcerExonerationTVA($data);

        \Log::info("=== APRÈS mutateFormDataBeforeSave ===", [
            'nb_lignes' => count($data['lignes'] ?? []),
        ]);

        return $data;
    }

    // protected function afterSave(): void
    // {
    //     // Recharger les lignes depuis la BD
    //     $this->record->refresh();
    //     $this->record->load('lignes');

    //     \Log::info("=== APRÈS SAUVEGARDE BC ===", [
    //         'bc_numero' => $this->record->numero,
    //         'nb_lignes_bd_apres' => $this->record->lignes()->count(),
    //         'lignes_bd' => $this->record->lignes->map(fn($l) => [
    //             'id' => $l->id,
    //             'designation' => $l->designation,
    //             'quantite' => $l->quantite,
    //         ])->toArray(),
    //     ]);

    //     // Notification avec le nombre de lignes
    //     \Filament\Notifications\Notification::make()
    //         ->title('Debug Info')
    //         ->body("Lignes en BD : {$this->record->lignes()->count()}")
    //         ->info()
    //         ->send();
    // }

    // ✅ CONSERVÉ : Forcer l'exonération TVA
    // protected function mutateFormDataBeforeSave(array $data): array
    // {
    //     // ✅ Forcer l'exonération TVA AVANT sauvegarde
    //     return self::forcerExonerationTVA($data);
    // }

    // ✅ CONSERVÉ : Méthode de traitement de l'exonération TVA
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
