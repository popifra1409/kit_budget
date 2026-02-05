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

        // ✅ Forcer l’exonération TVA AVANT enregistrement
        return self::forcerExonerationTVA($data);
    }

    protected static function forcerExonerationTVA(array $data): array
    {
        if (!empty($data['exonere_tva'])) {
            $data['montant_tva'] = 0;

            foreach ($data['lignes'] ?? [] as &$ligne) {
                $ligne['taux_tva'] = 0;
                $ligne['montant_tva'] = 0;
                $ligne['montant_ttc'] = $ligne['montant_ht'] ?? 0;
            }
        }

        return $data;
    }
}
