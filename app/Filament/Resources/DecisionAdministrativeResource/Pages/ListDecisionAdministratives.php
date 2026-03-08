<?php

namespace App\Filament\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Resources\DecisionAdministrativeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDecisionAdministratives extends ListRecords
{
    protected static string $resource = DecisionAdministrativeResource::class;

    /**
     * ✅ Actions dans l'en-tête de la page (bouton Créer)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle décision')
                ->icon('heroicon-o-plus'),
        ];
    }
}
