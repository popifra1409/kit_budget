<?php

namespace App\Filament\Resources\MemoireDepenseResource\Pages;

use App\Filament\Resources\MemoireDepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMemoireDepense extends CreateRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire créé')
            ->body('Le mémoire de dépense a été créé avec succès.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // S'assurer que les champs par défaut sont définis
        $data['statut'] = $data['statut'] ?? 'brouillon';

        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalculer les totaux après création
        $this->record->calculerTotaux();
        $this->record->save();
    }
}
