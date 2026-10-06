<?php

namespace App\Filament\Budget\Resources\ClotureExerciceResource\Pages;

use App\Filament\Budget\Resources\ClotureExerciceResource;
use App\Models\Budget;
use App\Models\Exercice;
use App\Services\Budget\ClotureExerciceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClotureExercice extends CreateRecord
{
    protected static string $resource = ClotureExerciceResource::class;

    /** Création et calcul de la situation de toutes les lignes. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ClotureExerciceService::class)->preparer(
                Exercice::findOrFail($data['exercice_id']),
                Budget::withoutGlobalScope('exercice')->findOrFail($data['budget_id'])
            );
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Préparation impossible')->body($e->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
