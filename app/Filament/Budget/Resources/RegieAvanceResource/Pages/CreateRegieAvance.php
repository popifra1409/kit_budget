<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegieAvance extends CreateRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'rav'; // ← forcer le type
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
