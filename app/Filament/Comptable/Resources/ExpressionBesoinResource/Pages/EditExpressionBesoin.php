<?php

namespace App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;

use App\Filament\Comptable\Resources\ExpressionBesoinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpressionBesoin extends EditRecord
{
    protected static string $resource = ExpressionBesoinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->syncLegacyServiceDemandeur($data);
    }

    protected function syncLegacyServiceDemandeur(array $data): array
    {
        if (!empty($data['service_demandeur_id'])) {
            $service = \App\Models\Service::find($data['service_demandeur_id']);
            $data['service_demandeur'] = $service?->nom;
        }
        return $data;
    }
}
