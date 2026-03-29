<?php

namespace App\Filament\Comptable\Resources\OrdreEntreeResource\Pages;

use App\Filament\Comptable\Resources\OrdreEntreeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewOrdreEntree extends ViewRecord
{
    protected static string $resource = OrdreEntreeResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn() => $this->record->estModifiable()),
        ];
    }
}
