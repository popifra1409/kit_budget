<?php

namespace App\Filament\Budget\Resources\BordereauEngagementResource\Pages;

use App\Filament\Budget\Resources\BordereauEngagementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBordereauEngagement extends CreateRecord
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Bordereau créé avec succès';
    }

    /**
     * ✅ AJOUT : Recalculer les montants après création + sync engagements
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Recalculer après que les engagements ont été attachés
        $record->recalculerMontants();
    }
}
