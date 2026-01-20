<?php

namespace App\Filament\Resources\ParametresFournisseurResource\Pages;

use App\Filament\Resources\ParametresFournisseurResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateParametresFournisseur extends CreateRecord
{
    protected static string $resource = ParametresFournisseurResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Paramètres fournisseur créés')
            ->body('Les informations du fournisseur ont été enregistrées avec succès.');
    }
}
