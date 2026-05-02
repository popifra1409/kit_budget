<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuDepense extends CreateRecord
{
    protected static string $resource = MenuDepenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'menu_depense';
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
