<?php

namespace App\Filament\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Resources\DecisionAdministrativeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDecisionAdministrative extends CreateRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return self::calculerMontants($data);
    }

    /**
     * Calcul CNPS / IRNC / Taxes / Net
     */
    protected static function calculerMontants(array $data): array
    {
        $brut = (float) ($data['montant_brut'] ?? 0);
        $tauxCnps = (float) ($data['taux_cnps'] ?? 4.2);
        $tauxIrnc = (float) ($data['taux_irnc'] ?? 11);
        $autresRetenues = (float) ($data['autres_retenues'] ?? 0);

        $data['montant_cnps'] = $brut * ($tauxCnps / 100);
        $data['montant_irnc'] = $brut * ($tauxIrnc / 100);

        $data['total_taxes'] =
            $data['montant_cnps'] +
            $data['montant_irnc'] +
            $autresRetenues;

        $data['montant_net'] = $brut - $data['total_taxes'];

        return $data;
    }
}
