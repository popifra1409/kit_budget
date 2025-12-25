<?php

namespace App\Filament\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Resources\VirementBudgetaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVirementBudgetaire extends CreateRecord
{
    protected static string $resource = VirementBudgetaireResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['statut'] = 'en_attente';
        return $data;
    }
}
