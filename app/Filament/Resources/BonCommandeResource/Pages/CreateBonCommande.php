<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBonCommande extends CreateRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['statut'] = 'brouillon';
        return $data;
    }
}
