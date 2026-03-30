<?php

namespace App\Filament\Budget\Resources\DecisionPrevisionnelleResource\Pages;

use App\Filament\Budget\Resources\DecisionPrevisionnelleResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditDecisionPrevisionnelle extends EditRecord
{
    protected static string $resource = DecisionPrevisionnelleResource::class;

    // ✅ Conserver est_previsionnel = true à la mise à jour
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['est_previsionnel'] = true;

        if (($data['mode_saisie'] ?? 'calcule') === 'forfait') {
            $data['taux_tva'] = $data['taux_cnps'] = $data['taux_irnc'] = 0;
            $data['type_tva'] = 'forfait';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }
}