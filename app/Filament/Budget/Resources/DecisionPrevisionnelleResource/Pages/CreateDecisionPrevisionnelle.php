<?php

namespace App\Filament\Budget\Resources\DecisionPrevisionnelleResource\Pages;

use App\Filament\Budget\Resources\DecisionPrevisionnelleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDecisionPrevisionnelle extends CreateRecord
{
    protected static string $resource = DecisionPrevisionnelleResource::class;

    // ✅ Forcer est_previsionnel = true à la création
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['est_previsionnel'] = true;
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('🔮 Décision prévisionnelle créée')
            ->body("N° {$this->record->numero} — aucun impact budget.");
    }
}                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           