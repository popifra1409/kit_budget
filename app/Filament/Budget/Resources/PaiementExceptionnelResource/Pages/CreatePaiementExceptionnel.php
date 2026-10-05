<?php

namespace App\Filament\Budget\Resources\PaiementExceptionnelResource\Pages;

use App\Filament\Budget\Resources\PaiementExceptionnelResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaiementExceptionnel extends CreateRecord
{
    protected static string $resource = PaiementExceptionnelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['statut' => 'brouillon'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
