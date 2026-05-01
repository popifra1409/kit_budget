<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use App\Filament\Budget\Resources\DecisionAdministrativeResource\Concerns\GereCalculsMontants;
use Filament\Resources\Pages\CreateRecord;

class CreateDecisionAdministrative extends CreateRecord
{
    use GereCalculsMontants;

    protected static string $resource = DecisionAdministrativeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '✅ Décision créée avec succès';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->preparerDonnees($data);
    }
}
