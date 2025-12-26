<?php

namespace App\Filament\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Resources\DecisionAdministrativeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDecisionAdministrative extends EditRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
