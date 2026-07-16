<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use App\Models\Transmission;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ApercuDecisionAdministrative extends ViewRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    protected static ?string $title = 'Aperçu avant validation';

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Confirmer la validation
            Action::make('confirmer_validation')
                ->label('✅ Confirmer la validation')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn() => $this->record->statut === 'brouillon'
                    && auth()->user()?->can('valider_decision_administrative'))
                ->requiresConfirmation()
                ->modalHeading('Confirmer la validation')
                ->modalDescription(
                    fn() =>
                    "Valider la décision N° {$this->record->numero} "
                        . "pour {$this->record->getNomCompletPersonnel()} "
                        . "d'un montant net de "
                        . number_format($this->record->montant_net ?? 0, 0, ',', ' ')
                        . " FCFA ?"
                )
                ->modalSubmitActionLabel('✅ Oui, valider')
                ->action(function () {
                    try {
                        $this->record->valider(auth()->user());

                        // Clôturer transmission si en attente de validation
                        Transmission::where('document_id', $this->record->id)
                            ->where('destinataire_id', auth()->id())
                            ->where('statut', 'en_attente')
                            ->where('action_attendue', 'validation')
                            ->where(function ($q) {
                                $q->where('document_type', get_class($this->record))
                                    ->orWhere('document_type', 'decision_administrative');
                            })
                            ->first()
                            ?->traiter('Document validé');

                        Notification::make()
                            ->title('✅ Décision validée')
                            ->success()
                            ->body("La DA {$this->record->numero} a été validée avec succès.")
                            ->send();

                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record])
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

            // ✅ Retour sans valider
            Action::make('retour')
                ->label('← Retour — Modifier')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => DecisionAdministrativeResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function getView(): string
    {
        return 'filament.pages.apercu-decision-administrative-page';
    }
}
