<?php

namespace App\Filament\Budget\Resources\AchatDirectResource\Pages;

use App\Filament\Budget\Resources\AchatDirectResource;
use App\Models\ProvisionLigneRegie;
use Filament\Resources\Pages\EditRecord;

class EditAchatDirect extends EditRecord
{
    protected static string $resource = AchatDirectResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        // ✅ Ordre important : regie_avance_id en premier
        $data['regie_avance_id']          = $record->regie_avance_id;
        $data['provision_ligne_regie_id'] = $record->provision_ligne_regie_id;
        $data['ligne_regie_avance_id']    = $record->ligne_regie_avance_id;

        // Fallback ligne_regie si null
        if (empty($data['ligne_regie_avance_id']) && $record->provision_ligne_regie_id) {
            $prov = \App\Models\ProvisionLigneRegie::find($record->provision_ligne_regie_id);
            $data['ligne_regie_avance_id'] = $prov?->ligne_regie_avance_id;
        }

        $data['mode_saisie_global'] = $record->mode_saisie ?? 'montant_nap';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Nettoyer uniquement les champs obsolètes
        unset(
            $data['net_a_payer_input'],
            $data['mode_saisie_montant'],
            $data['montant_ht_input'],
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
