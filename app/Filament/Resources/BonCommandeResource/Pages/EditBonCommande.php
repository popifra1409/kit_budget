<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBonCommande extends EditRecord
{
    protected static string $resource = BonCommandeResource::class;


    protected function getHeaderActions(): array
    {
        return [
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

    // ✅ MODIFIÉ : Redirection vers 'view' seulement si sauvegarde normale
    protected function getRedirectUrl(): string
    {
        // Si c'est une sauvegarde normale (bouton "Sauvegarder"), rediriger vers view
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
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

    // ✅ CONSERVÉ : Forcer l'exonération TVA
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Forcer l'exonération TVA AVANT sauvegarde
        return self::forcerExonerationTVA($data);
    }

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
