<?php

namespace App\Filament\Budget\Resources\AchatDirectResource\Pages;

use App\Filament\Budget\Resources\AchatDirectResource;
use Filament\Resources\Pages\EditRecord;

class EditAchatDirect extends EditRecord
{
    protected static string $resource = AchatDirectResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['net_a_payer_input'], $data['mode_saisie_montant'], $data['montant_ht_input']);
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
