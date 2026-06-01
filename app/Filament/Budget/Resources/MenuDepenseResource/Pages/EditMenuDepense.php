<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Resources\Pages\EditRecord;

class EditMenuDepense extends EditRecord
{
    protected static string $resource = MenuDepenseResource::class;

    // ✅ Au chargement — pas de sync montant_alloue
    // car il est calculé depuis la SOMME des DA sources (pas depuis une seule DA)
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ✅ Sync objet depuis libelle si vide
        if (empty($data['objet']) && !empty($data['libelle'])) {
            $data['objet'] = $data['libelle'];
        }

        // ✅ Recalculer montant_alloue = somme des DA sources liées
        $totalDaSources = \App\Models\MenuDepenseDecision::where(
            'regie_avance_id',
            $this->record->id
        )->sum('montant_da');

        if ($totalDaSources > 0) {
            $data['montant_alloue'] = $totalDaSources;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ✅ Ne PAS écraser montant_alloue — il vient des DA sources
        // Seulement recalculer si on a des DA sources
        $totalDaSources = \App\Models\MenuDepenseDecision::where(
            'regie_avance_id',
            $this->record->id
        )->sum('montant_da');

        if ($totalDaSources > 0) {
            $data['montant_alloue'] = $totalDaSources;
        }

        return $data;
    }
}
