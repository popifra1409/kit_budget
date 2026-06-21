<?php

namespace App\Filament\Comptable\Resources\FicheConsolidationBesoinResource\Pages;

use App\Filament\Comptable\Resources\FicheConsolidationBesoinResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFicheConsolidationBesoin extends ViewRecord
{
    protected static string $resource = FicheConsolidationBesoinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'ouverte'),
        ];
    }
}
