<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateRegieAvance extends CreateRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'rav';
        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('✅ Régie d\'Avance créée')
            ->success()
            ->body(
                'Associez maintenant la décision source depuis '
                    . 'l\'onglet "Décision source" ci-dessous.'
            )
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
