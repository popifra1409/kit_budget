<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Resources\Pages\EditRecord;

class EditRegieAvance extends EditRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $regie = $this->record;

        if ($regie->decision_administrative_id) {
            $da = \App\Models\DecisionAdministrative::find(
                $regie->decision_administrative_id
            );

            if ($da) {
                // ✅ Sync uniquement montant_alloue depuis la DA
                if (empty($data['montant_alloue']) || $data['montant_alloue'] == 0) {
                    $data['montant_alloue'] = $da->montant_net;
                }
                // ❌ encaisse_annuelle NON touchée — saisie manuelle uniquement
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $daId = $data['decision_administrative_id']
            ?? $this->record->decision_administrative_id;

        if ($daId) {
            $da = \App\Models\DecisionAdministrative::find($daId);
            if ($da && (empty($data['montant_alloue']) || $data['montant_alloue'] == 0)) {
                // ✅ Sync uniquement montant_alloue
                $data['montant_alloue'] = $da->montant_net;
                // ❌ encaisse_annuelle NON touchée
            }
        }

        return $data;
    }
}
