<?php

namespace App\Filament\Programmation\Resources\CbmtExerciceResource\Pages;

use App\Filament\Programmation\Resources\CbmtExerciceResource;
use App\Models\CbmtExercice;
use App\Services\Programmation\GenerationCbmtService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCbmtExercice extends EditRecord
{
    protected static string $resource = CbmtExerciceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Le bandeau de la fiche invite au rattrapage, ce bouton en est le moyen. Il reste posé hors
            // brouillon : le refus doit être dit à l'opérateur, pas remplacé par un bouton absent.
            Actions\Action::make('rattraper')
                ->label('Rattraper les prévisions actualisées')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Rattraper les prévisions actualisées du CBMT')
                ->modalDescription(fn (CbmtExercice $record) => $record->alerteCheminCredits()['message']
                    . ' Seule la colonne « prévision ' . $record->anneeReference() . ' actualisée » est reprise :'
                    . ' les projections et la prévision votée restent telles que vous les avez saisies.')
                ->modalSubmitActionLabel('Rattraper')
                ->action(function (CbmtExercice $record) {
                    $service = app(GenerationCbmtService::class);
                    $bilan = $service->actualiser((int) $record->exercice_reference_id);

                    Notification::make()
                        ->title('Prévisions actualisées — ' . $record->numero)
                        ->body($service->resumeBilan($bilan))
                        ->color(($bilan['refuses'] ?? []) !== [] || ($bilan['sans_compte'] ?? 0) > 0
                            || ($bilan['ecartees'] ?? 0) > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
