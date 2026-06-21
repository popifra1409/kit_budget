<?php

namespace App\Filament\Comptable\Resources\UniteMesureResource\Pages;

use App\Filament\Comptable\Resources\UniteMesureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUniteMesure extends EditRecord
{
    protected static string $resource = UniteMesureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
