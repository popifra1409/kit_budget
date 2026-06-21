<?php

namespace App\Filament\Comptable\Resources\FicheConsolidationBesoinResource\Pages;

use App\Filament\Comptable\Resources\FicheConsolidationBesoinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFicheConsolidationBesoin extends EditRecord
{
    protected static string $resource = FicheConsolidationBesoinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
