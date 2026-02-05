<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBonCommande extends EditRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->statut === 'brouillon'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Forcer l’exonération TVA AVANT sauvegarde
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
