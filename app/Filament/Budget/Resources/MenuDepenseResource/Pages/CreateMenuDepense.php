<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMenuDepense extends CreateRecord
{
    protected static string $resource = MenuDepenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'menu_depense';
        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('✅ Menu Dépense créé')
            ->success()
            ->body(
                'Ajoutez maintenant les décisions sources depuis '
                    . 'l\'onglet "Décisions sources" ci-dessous.'
            )
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
