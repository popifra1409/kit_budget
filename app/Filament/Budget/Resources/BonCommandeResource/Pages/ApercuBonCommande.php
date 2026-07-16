<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ApercuBonCommande extends ViewRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected static ?string $title = 'Aperçu avant validation';

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Bouton Confirmer la validation
            Action::make('confirmer_validation')
                ->label('✅ Confirmer la validation')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn() => $this->record->statut === 'brouillon'
                    && BonCommandeResource::canValider($this->record))
                ->requiresConfirmation()
                ->modalHeading('Confirmer la validation')
                ->modalDescription(fn() => "Valider définitivement le BC N° {$this->record->numero} ?")
                ->modalSubmitActionLabel('✅ Oui, valider')
                ->action(function () {
                    try {
                        $this->record->valider(auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('✅ BC validé')
                            ->success()
                            ->body("Le BC {$this->record->numero} a été validé avec succès.")
                            ->send();

                        // Rediriger vers ViewBonCommande
                        $this->redirect(
                            BonCommandeResource::getUrl('view', ['record' => $this->record])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // ✅ Bouton Retour — modifier le BC
            Action::make('retour_modifier')
                ->label('← Retour — Modifier')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => BonCommandeResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    // Utiliser la vue aperçu dédiée
    public function getView(): string
    {
        return 'filament.pages.apercu-bon-commande-page';
    }
}
